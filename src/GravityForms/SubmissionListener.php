<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\GravityForms;

use Kodr\SecureReferralArchive\Queue\QueueRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Listens for Gravity Forms submissions and queues archiving for enabled
 * forms only. Must return quickly — no parsing, archive generation, or AWS
 * calls happen here. Those run later, in the background queue worker.
 */
final class SubmissionListener
{
    public const PROCESS_QUEUE_HOOK = 'kodr_sra_process_queue';

    public function __construct(
        private readonly FormArchiveSettings $settings = new FormArchiveSettings(),
        private readonly QueueRepository $queue = new QueueRepository()
    ) {
    }

    public static function hooks(): void
    {
        add_action('gform_after_submission', [new self(), 'handle'], 10, 2);
    }

    /**
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $form
     */
    public function handle(array $entry, array $form): void
    {
        if (!$this->settings->isEnabledForForm($form)) {
            return;
        }

        $formId = (int) ($form['id'] ?? 0);
        $entryId = (int) ($entry['id'] ?? 0);

        if ($formId <= 0 || $entryId <= 0) {
            return;
        }

        $this->queue->enqueue($formId, $entryId);
        $this->scheduleProcessing();
    }

    /**
     * Schedules a single near-immediate background event so archiving starts
     * promptly, without waiting for the next recurring cron tick. Safe to
     * call repeatedly — only ever schedules one pending event at a time.
     */
    private function scheduleProcessing(): void
    {
        if (wp_next_scheduled(self::PROCESS_QUEUE_HOOK)) {
            return;
        }

        wp_schedule_single_event(time(), self::PROCESS_QUEUE_HOOK);
    }
}
