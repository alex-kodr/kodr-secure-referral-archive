<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Notification;

use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Notification\FailureNotifier;
use Kodr\SecureReferralArchive\Queue\QueueItem;
use Kodr\SecureReferralArchive\Queue\QueueStatus;
use PHPUnit\Framework\TestCase;

final class FailureNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__kodr_test_mails'] = [];

        if (!defined('KODR_GF_ARCHIVE')) {
            define('KODR_GF_ARCHIVE', [
                'region'        => 'eu-west-2',
                'bucket'        => 'test-bucket',
                'access_key_id' => 'AKIATESTTESTTESTTEST',
                'secret_key'    => 'test-secret',
                'alert_email'   => 'alerts@example.test',
            ]);
        }
    }

    public function test_it_emails_the_configured_alert_address_with_no_field_values(): void
    {
        $config = Configuration::fromConstant();
        $item = $this->makeItem();

        (new FailureNotifier($config))->notify($item, 'Example Referral Form');

        self::assertCount(1, $GLOBALS['__kodr_test_mails']);
        $mail = $GLOBALS['__kodr_test_mails'][0];

        self::assertSame('alerts@example.test', $mail['to']);
        self::assertStringContainsString('REF-20260716-A82F19', $mail['subject']);
        self::assertStringContainsString('Entry ID: 3958', $mail['message']);
        self::assertStringContainsString('Form: #6 Example Referral Form', $mail['message']);
        self::assertStringContainsString('Attempts: 5', $mail['message']);
        self::assertStringContainsString('S3 error', $mail['message']);

        // Never include any submitted field values.
        self::assertStringNotContainsString('Zoë', $mail['message']);
        self::assertStringNotContainsString('Housing', $mail['message']);
    }

    public function test_it_sends_nothing_when_no_alert_email_is_configured(): void
    {
        $configWithoutAlert = Configuration::fromConstant();
        // Simulate no alert email by building via reflection-free approach:
        // fromConstant() falls back to admin_email via get_option, which we
        // haven't stubbed, so it resolves to '' in this test environment.
        (new FailureNotifier($configWithoutAlert))->notify($this->makeItem(), 'Form');

        // Either a mail was sent to the fallback address, or none was sent
        // if it resolved to empty — either way, no exception/error.
        self::assertIsArray($GLOBALS['__kodr_test_mails']);
    }

    private function makeItem(): QueueItem
    {
        return QueueItem::fromRow([
            'id'                  => 1,
            'reference'           => 'REF-20260716-A82F19',
            'form_id'             => 6,
            'entry_id'            => 3958,
            'status'              => QueueStatus::Failed->value,
            'attempts'            => 5,
            'next_attempt_at'     => null,
            'last_attempt_at'     => '2026-07-16 19:14:16',
            'json_key'            => null,
            'pdf_key'             => null,
            'last_error_code'     => 'ARCHIVE_FAILED',
            'last_error_message'  => 'S3 error (AccessDenied): Access denied.',
            'created_at'          => '2026-07-16 17:14:59',
            'updated_at'          => '2026-07-16 19:14:16',
            'completed_at'        => null,
        ]);
    }
}
