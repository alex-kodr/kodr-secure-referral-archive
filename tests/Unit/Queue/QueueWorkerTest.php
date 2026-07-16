<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Queue;

use Kodr\SecureReferralArchive\Archive\ArchiveProcessor;
use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Queue\QueueWorker;
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

    private function worker(): QueueWorker
    {
        $processor = new ArchiveProcessor($this->queue, $this->config, $this->storage);

        return new QueueWorker($this->queue, $processor);
    }
}
