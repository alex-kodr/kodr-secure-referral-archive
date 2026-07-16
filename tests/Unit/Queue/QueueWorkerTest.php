<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Queue;

use Kodr\SecureReferralArchive\Archive\ArchiveProcessor;
use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Notification\FailureNotifier;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Queue\QueueWorker;
use Kodr\SecureReferralArchive\Queue\RetryPolicy;
use Kodr\SecureReferralArchive\Tests\Fixtures\ReferralFormFixture;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryStorage;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

final class QueueWorkerTest extends TestCase
{
    private QueueRepository $queue;
    private InMemoryStorage $storage;
    private Configuration $config;

    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new InMemoryWpdb();
        $GLOBALS['__kodr_test_transients'] = [];
        $GLOBALS['__kodr_test_mails'] = [];

        $this->queue = new QueueRepository();
        $this->storage = new InMemoryStorage();

        \GFAPI::$forms = [];
        \GFAPI::$entries = [];

        if (!defined('KODR_GF_ARCHIVE')) {
            define('KODR_GF_ARCHIVE', [
                'region'        => 'eu-west-2',
                'bucket'        => 'test-bucket',
                'prefix'        => 'referrals',
                'access_key_id' => 'AKIATESTTESTTESTTEST',
                'secret_key'    => 'test-secret',
                'alert_email'   => 'alerts@example.test',
            ]);
        }

        $this->config = Configuration::fromConstant();
    }

    public function test_it_processes_due_items_and_marks_them_completed(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);

        $this->worker()->run();

        $updated = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Completed, $updated->status());
        self::assertCount(2, $this->storage->stored);
    }

    public function test_it_reschedules_a_failed_item_as_retry(): void
    {
        // No form registered for id 42 -> processing must fail.
        $item = $this->queue->enqueue(42, 1);

        $this->worker()->run();

        $updated = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Retry, $updated->status());
        self::assertSame(1, $updated->attempts());
        self::assertNotNull($updated->nextAttemptAt());
        self::assertNotNull($updated->lastErrorMessage());
    }

    public function test_it_does_nothing_while_locked(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);

        set_transient('kodr_sra_queue_worker_lock', 1);

        $this->worker()->run();

        $updated = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Pending, $updated->status(), 'locked worker must not touch the queue at all');
        self::assertSame([], $this->storage->stored);
    }

    public function test_it_only_processes_a_batch_at_a_time(): void
    {
        [$form, $entryTemplate] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;

        $items = [];
        for ($entryId = 1; $entryId <= 6; $entryId++) {
            $entry = $entryTemplate;
            $entry['id'] = $entryId;
            \GFAPI::$entries[$entryId] = $entry;
            $items[] = $this->queue->enqueue(6, $entryId);
        }

        $this->worker()->run();

        $completed = 0;
        $pending = 0;
        foreach ($items as $item) {
            $status = $this->queue->findById($item->id())->status();
            if ($status === QueueStatus::Completed) {
                $completed++;
            } elseif ($status === QueueStatus::Pending) {
                $pending++;
            }
        }

        self::assertSame(5, $completed, 'only one batch (5 items) should be processed per run');
        self::assertSame(1, $pending, 'the remaining item must be left for the next run');
    }

    public function test_it_sends_exactly_one_failure_notification_after_the_final_retry(): void
    {
        // No form registered for id 42 -> every attempt fails.
        $item = $this->queue->enqueue(42, 1);

        // Drive through all 5 attempts by running the worker repeatedly and
        // forcing each retry to be immediately due.
        for ($i = 0; $i < 5; $i++) {
            $this->worker()->run();
            $current = $this->queue->findById($item->id());
            if ($current->status() === QueueStatus::Failed) {
                break;
            }
            // Force the scheduled retry to be due right now so the next
            // loop iteration picks it straight back up.
            $GLOBALS['wpdb']->query("UPDATE wp_kodr_sra_queue SET next_attempt_at = '2000-01-01 00:00:00' WHERE id = {$item->id()}");
        }

        $final = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Failed, $final->status());
        self::assertSame(5, $final->attempts());

        self::assertCount(1, $GLOBALS['__kodr_test_mails'], 'exactly one failure notification must be sent');
        self::assertStringContainsString($item->reference(), $GLOBALS['__kodr_test_mails'][0]['subject']);
    }

    private function worker(): QueueWorker
    {
        $processor = new ArchiveProcessor($this->queue, $this->config, $this->storage);

        return new QueueWorker($this->queue, $processor, new RetryPolicy(), new FailureNotifier($this->config));
    }
}
