<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Queue;

use Kodr\SecureReferralArchive\Queue\QueueCleanup;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

final class QueueCleanupTest extends TestCase
{
    private QueueRepository $queue;
    private InMemoryWpdb $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        $this->queue = new QueueRepository();
    }

    public function test_it_deletes_completed_items_older_than_90_days(): void
    {
        $old = $this->queue->enqueue(6, 1);
        $this->queue->markProcessing($old->id());
        $this->queue->markCompleted($old->id(), 'json', 'pdf');
        $this->backdate($old->id(), 'completed_at', '-120 days');
        $this->backdate($old->id(), 'updated_at', '-120 days');

        $recent = $this->queue->enqueue(6, 2);
        $this->queue->markProcessing($recent->id());
        $this->queue->markCompleted($recent->id(), 'json', 'pdf');

        (new QueueCleanup($this->queue))->run();

        self::assertNull($this->queue->findById($old->id()), 'completed item older than 90 days must be pruned');
        self::assertNotNull($this->queue->findById($recent->id()), 'recently completed item must be kept');
    }

    public function test_it_deletes_failed_items_not_touched_in_90_days(): void
    {
        $old = $this->queue->enqueue(6, 1);
        $this->queue->markProcessing($old->id());
        $this->queue->markFailed($old->id(), 'ARCHIVE_FAILED', 'gone');
        $this->backdate($old->id(), 'updated_at', '-120 days');

        $recent = $this->queue->enqueue(6, 2);
        $this->queue->markProcessing($recent->id());
        $this->queue->markFailed($recent->id(), 'ARCHIVE_FAILED', 'gone');

        (new QueueCleanup($this->queue))->run();

        self::assertNull($this->queue->findById($old->id()), 'failed item untouched for 90+ days must be pruned');
        self::assertNotNull($this->queue->findById($recent->id()), 'recently failed item must be kept for admin attention');
    }

    public function test_it_never_touches_pending_retry_or_processing_items(): void
    {
        $pending = $this->queue->enqueue(6, 1);
        $this->backdate($pending->id(), 'created_at', '-200 days');
        $this->backdate($pending->id(), 'updated_at', '-200 days');

        (new QueueCleanup($this->queue))->run();

        self::assertNotNull($this->queue->findById($pending->id()), 'non-terminal items must never be pruned regardless of age');
        self::assertSame(QueueStatus::Pending, $this->queue->findById($pending->id())->status());
    }

    private function backdate(int $id, string $column, string $modify): void
    {
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify($modify)->format('Y-m-d H:i:s');
        $this->wpdb->query("UPDATE wp_kodr_sra_queue SET {$column} = '{$timestamp}' WHERE id = {$id}");
    }
}
