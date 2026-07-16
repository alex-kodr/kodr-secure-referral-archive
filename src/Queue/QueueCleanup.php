<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prunes old queue metadata rows on a daily schedule. Never touches S3
 * objects or Gravity Forms entries — only rows in wp_kodr_sra_queue.
 *
 *   completed rows: pruned 90 days after completion
 *   failed rows:    pruned 90 days after the last attempt, giving an admin
 *                   ample time to notice the failure email and act on it
 */
final class QueueCleanup
{
    public const CRON_HOOK = 'kodr_sra_cleanup_queue';
    private const RETENTION_DAYS = 90;

    public function __construct(private readonly QueueRepository $queue = new QueueRepository())
    {
    }

    public static function hooks(): void
    {
        add_action('init', [self::class, 'ensureScheduled']);
        add_action(self::CRON_HOOK, [self::class, 'runScheduled']);
    }

    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time(), 'daily', self::CRON_HOOK);
    }

    public static function runScheduled(): void
    {
        (new self())->run();
    }

    public function run(): void
    {
        $cutoff = $this->cutoff();

        $this->queue->deleteCompletedBefore($cutoff);
        $this->queue->deleteFailedBefore($cutoff);
    }

    private function cutoff(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d days', self::RETENTION_DAYS))
            ->format('Y-m-d H:i:s');
    }
}
