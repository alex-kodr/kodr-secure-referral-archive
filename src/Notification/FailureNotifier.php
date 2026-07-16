<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Notification;

use Kodr\SecureReferralArchive\Config\Configuration;
use Kodr\SecureReferralArchive\Queue\QueueItem;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sends a single non-sensitive email when a queue item permanently fails
 * (all retry attempts exhausted). Never includes submitted field values —
 * only the operational metadata already considered safe to store in the
 * queue table.
 */
final class FailureNotifier
{
    public function __construct(private readonly Configuration $config)
    {
    }

    public function notify(QueueItem $item, string $formTitle): void
    {
        $to = $this->config->alertEmail();
        if ($to === '' || !function_exists('wp_mail')) {
            return;
        }

        $subject = sprintf('[%s] Referral archive failed: %s', $this->siteName(), $item->reference());

        $lines = [
            sprintf('Site: %s', $this->siteUrl()),
            sprintf('Form: #%d %s', $item->formId(), $formTitle),
            sprintf('Entry ID: %d', $item->entryId()),
            sprintf('Reference: %s', $item->reference()),
            sprintf('Attempts: %d', $item->attempts()),
            sprintf('Error: %s', $item->lastErrorMessage() ?? 'Unknown error'),
            '',
            sprintf('Review in wp-admin: %s', $this->adminQueueUrl()),
        ];

        wp_mail($to, $subject, implode("\n", $lines));
    }

    private function siteName(): string
    {
        return function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
    }

    private function siteUrl(): string
    {
        return function_exists('home_url') ? home_url('/') : '';
    }

    private function adminQueueUrl(): string
    {
        return function_exists('admin_url') ? admin_url('admin.php?page=kodr-secure-referral-archive') : '';
    }
}
