<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Cron;

use Kodr\SecureReferralArchive\GravityForms\SubmissionListener;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers a custom "every 5 minutes" WP-Cron schedule and ensures the
 * recurring queue-processing event is scheduled. Uses the same hook that
 * SubmissionListener schedules a near-immediate single event on, so
 * QueueWorker only needs to listen to one hook.
 */
final class Scheduler
{
    private const SCHEDULE_ID = 'kodr_sra_five_minutes';

    public static function hooks(): void
    {
        add_filter('cron_schedules', [self::class, 'addSchedule']); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected
        add_action('init', [self::class, 'ensureScheduled']);
    }

    /**
     * @param array<string,array{interval:int,display:string}> $schedules
     * @return array<string,array{interval:int,display:string}>
     */
    public static function addSchedule(array $schedules): array
    {
        $schedules[self::SCHEDULE_ID] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __('Every 5 minutes (Kodr Secure Referral Archive)', 'kodr-secure-referral-archive'),
        ];

        return $schedules;
    }

    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(SubmissionListener::PROCESS_QUEUE_HOOK)) {
            return;
        }

        wp_schedule_event(time(), self::SCHEDULE_ID, SubmissionListener::PROCESS_QUEUE_HOOK);
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(SubmissionListener::PROCESS_QUEUE_HOOK);
        if ($timestamp !== false) {
            wp_unschedule_event($timestamp, SubmissionListener::PROCESS_QUEUE_HOOK);
        }
    }
}
