<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Tests\Unit\Queue;

use Kodr\SecureReferralArchive\Queue\RetryPolicy;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function test_it_allows_four_retries_after_the_first_failed_attempt(): void
    {
        $policy = new RetryPolicy();

        self::assertTrue($policy->shouldRetry(1));
        self::assertTrue($policy->shouldRetry(2));
        self::assertTrue($policy->shouldRetry(3));
        self::assertTrue($policy->shouldRetry(4));
    }

    public function test_it_gives_up_after_the_fifth_attempt(): void
    {
        $policy = new RetryPolicy();

        self::assertFalse($policy->shouldRetry(5));
        self::assertFalse($policy->shouldRetry(6));
    }

    public function test_it_uses_the_documented_backoff_intervals(): void
    {
        $policy = new RetryPolicy();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $expectedMinutes = [
            1 => 15,
            2 => 60,
            3 => 6 * 60,
            4 => 12 * 60,
        ];

        foreach ($expectedMinutes as $attempts => $minutes) {
            $next = $policy->nextAttemptAt($attempts);
            $diffMinutes = ($next->getTimestamp() - $now->getTimestamp()) / 60;

            self::assertEqualsWithDelta($minutes, $diffMinutes, 1, "attempt {$attempts} should be delayed by {$minutes} minutes");
        }
    }
}
