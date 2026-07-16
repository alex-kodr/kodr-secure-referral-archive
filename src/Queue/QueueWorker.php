<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

use Kodr\SecureReferralArchive\Archive\ArchiveProcessingException;
use Kodr\SecureReferralArchive\Archive\ArchiveProcessor;
use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\GravityForms\SubmissionListener;
use Kodr\SecureReferralArchive\Storage\S3Storage;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Processes a small, locked batch of due queue items per run.
 *
 * A short-lived transient lock stops two overlapping cron executions from
 * working the same batch at once. QueueRepository::markProcessing() also
 * atomically claims each item, so even without the lock no two workers could
 * ever process the same item — the lock exists to avoid wasted duplicate
 * work, not for correctness.
 */
final class QueueWorker
{
    private const BATCH_SIZE = 5;
    private const LOCK_KEY = 'kodr_sra_queue_worker_lock';
    private const LOCK_TTL_SECONDS = 120;

    public function __construct(
        private readonly QueueRepository $queue,
        private readonly ArchiveProcessor $processor
    ) {
    }

    public static function hooks(): void
    {
        add_action(SubmissionListener::PROCESS_QUEUE_HOOK, [self::class, 'runScheduled']);
    }

    public static function runScheduled(): void
    {
        $config = Configuration::fromConstant();
        if (!$config->isValid()) {
            return;
        }

        $queue = new QueueRepository();
        $processor = new ArchiveProcessor($queue, $config, new S3Storage($config));

        (new self($queue, $processor))->run();
    }

    public function run(): void
    {
        if (!$this->acquireLock()) {
            return;
        }

        try {
            foreach ($this->queue->findDueItems(self::BATCH_SIZE) as $item) {
                $this->processOne($item);
            }
        } finally {
            $this->releaseLock();
        }
    }

    private function processOne(QueueItem $item): void
    {
        try {
            $this->processor->process($item);
        } catch (ArchiveProcessingException $exception) {
            // Interim handling only: a fixed short retry delay, capped at a
            // generous attempt count so nothing gets stuck forever. Replaced
            // in the next commit with the documented 15m/1h/6h/12h schedule.
            $current = $this->queue->findById($item->id());
            if ($current === null) {
                return;
            }

            if ($current->attempts() >= 10) {
                $this->queue->markFailed($current->id(), 'ARCHIVE_FAILED', $exception->getMessage());

                return;
            }

            $nextAttempt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+5 minutes');
            $this->queue->scheduleRetry($current->id(), $nextAttempt, 'ARCHIVE_FAILED', $exception->getMessage());
        }
    }

    private function acquireLock(): bool
    {
        if (get_transient(self::LOCK_KEY)) {
            return false;
        }

        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL_SECONDS);

        return true;
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_KEY);
    }
}
