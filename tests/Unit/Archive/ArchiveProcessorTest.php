<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Archive;

use Kodr\SecureReferralArchive\Archive\ArchiveProcessingException;
use Kodr\SecureReferralArchive\Archive\ArchiveProcessor;
use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Queue\QueueRepository;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use Kodr\SecureReferralArchive\Tests\Fixtures\ReferralFormFixture;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryStorage;
use Kodr\SecureReferralArchive\Tests\Support\InMemoryWpdb;
use PHPUnit\Framework\TestCase;

final class ArchiveProcessorTest extends TestCase
{
    private QueueRepository $queue;
    private InMemoryStorage $storage;
    private Configuration $config;
    private InMemoryWpdb $wpdb;

    protected function setUp(): void
    {
        $this->wpdb = new InMemoryWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->queue = new QueueRepository();
        $this->storage = new InMemoryStorage();

        \GFAPI::$forms = [];
        \GFAPI::$entries = [];
        \GFAPI::$deletedEntryIds = [];

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

    public function test_it_processes_a_queued_item_end_to_end(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);

        $processed = (new ArchiveProcessor($this->queue, $this->config, $this->storage))->process($item);

        self::assertTrue($processed);

        $updated = $this->queue->findById($item->id());
        self::assertSame(QueueStatus::Completed, $updated->status());
        self::assertNotNull($updated->jsonKey());
        self::assertNotNull($updated->pdfKey());
        self::assertStringStartsWith('referrals/form-6-', $updated->jsonKey());

        self::assertArrayHasKey($updated->jsonKey(), $this->storage->stored);
        self::assertArrayHasKey($updated->pdfKey(), $this->storage->stored);

        $jsonPayload = json_decode($this->storage->stored[$updated->jsonKey()]['contents'], true);
        self::assertSame($item->reference(), $jsonPayload['reference']);
        self::assertSame(6, $jsonPayload['form']['id']);

        self::assertStringStartsWith('%PDF-', $this->storage->stored[$updated->pdfKey()]['contents']);
    }

    public function test_it_permanently_deletes_the_source_entry_once_fully_archived(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);
        (new ArchiveProcessor($this->queue, $this->config, $this->storage))->process($item);

        self::assertSame([999], \GFAPI::$deletedEntryIds);
    }

    public function test_it_does_not_delete_the_source_entry_when_archiving_fails(): void
    {
        // No form registered for id 42 -> processing fails before completion.
        $item = $this->queue->enqueue(42, 1);
        \GFAPI::$entries[1] = ['id' => 1];

        try {
            (new ArchiveProcessor($this->queue, $this->config, $this->storage))->process($item);
        } catch (ArchiveProcessingException) {
            // expected
        }

        self::assertSame([], \GFAPI::$deletedEntryIds, 'the only copy of the data must not be deleted unless archiving succeeded');
    }

    public function test_it_does_not_reprocess_an_item_already_claimed_by_another_worker(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);
        self::assertTrue($this->queue->markProcessing($item->id()), 'simulate another worker claiming it first');

        $processed = (new ArchiveProcessor($this->queue, $this->config, $this->storage))->process($item);

        self::assertFalse($processed);
        self::assertSame([], $this->storage->stored, 'nothing should be uploaded when the claim fails');
    }

    public function test_it_throws_a_sanitized_exception_when_the_form_cannot_be_loaded(): void
    {
        $item = $this->queue->enqueue(42, 1);

        $processor = new ArchiveProcessor($this->queue, $this->config, $this->storage);

        $this->expectException(ArchiveProcessingException::class);

        try {
            $processor->process($item);
        } finally {
            $afterFailure = $this->queue->findById($item->id());
            self::assertSame(
                QueueStatus::Processing,
                $afterFailure->status(),
                'deciding retry vs. permanent failure is the caller\'s job, not ArchiveProcessor\'s'
            );
        }
    }

    public function test_it_never_writes_referral_field_values_into_the_queue_table(): void
    {
        [$form, $entry] = ReferralFormFixture::formAndEntry();
        \GFAPI::$forms[6] = $form;
        \GFAPI::$entries[999] = $entry;

        $item = $this->queue->enqueue(6, 999);
        (new ArchiveProcessor($this->queue, $this->config, $this->storage))->process($item);

        $row = $this->wpdb->get_row('SELECT * FROM wp_kodr_sra_queue WHERE id = ' . $item->id());
        self::assertNotNull($row);

        foreach ($row as $column => $value) {
            self::assertStringNotContainsString('Zoë', (string) $value, "column {$column} must never contain referral field values");
            self::assertStringNotContainsString('Housing', (string) $value, "column {$column} must never contain referral field values");
        }
    }
}
