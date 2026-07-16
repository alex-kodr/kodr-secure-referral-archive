<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Queue;

use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

final class QueueRepositoryPaginationTest extends TestCase
{
    private QueueRepository $queue;

    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new InMemoryWpdb();
        $this->queue = new QueueRepository();
    }

    public function test_it_paginates_all_items_across_statuses(): void
    {
        for ($entryId = 1; $entryId <= 7; $entryId++) {
            $this->queue->enqueue(6, $entryId);
        }

        $page1 = $this->queue->paginate(null, 1, 5);
        $page2 = $this->queue->paginate(null, 2, 5);

        self::assertSame(7, $page1['total']);
        self::assertCount(5, $page1['items']);
        self::assertCount(2, $page2['items']);
    }

    public function test_it_filters_by_status(): void
    {
        $a = $this->queue->enqueue(6, 1);
        $this->queue->enqueue(6, 2);
        $this->queue->markProcessing($a->id());
        $this->queue->markCompleted($a->id(), 'json-key', 'pdf-key');

        $completed = $this->queue->paginate(QueueStatus::Completed, 1, 10);
        $pending = $this->queue->paginate(QueueStatus::Pending, 1, 10);

        self::assertSame(1, $completed['total']);
        self::assertSame(1, $pending['total']);
    }

    public function test_manual_retry_resets_a_failed_item_to_pending(): void
    {
        $item = $this->queue->enqueue(6, 1);
        $this->queue->markProcessing($item->id());
        $this->queue->markFailed($item->id(), 'ARCHIVE_FAILED', 'Something went wrong.');

        $retried = $this->queue->retryNow($item->id());
        self::assertTrue($retried);

        $updated = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Pending, $updated->status());
        self::assertSame(0, $updated->attempts());
        self::assertNull($updated->nextAttemptAt());
        self::assertNull($updated->lastErrorMessage());
    }

    public function test_manual_retry_does_nothing_for_a_completed_item(): void
    {
        $item = $this->queue->enqueue(6, 1);
        $this->queue->markProcessing($item->id());
        $this->queue->markCompleted($item->id(), 'json-key', 'pdf-key');

        $retried = $this->queue->retryNow($item->id());

        self::assertFalse($retried);
        self::assertSame(QueueStatus::Completed, $this->queue->findById($item->id())->status());
    }
}
