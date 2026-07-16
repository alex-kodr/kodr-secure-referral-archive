<?php

declare(strict_types=1);

namespace Kodr\SecureReferralArchive\Queue;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The agreed retry schedule: five attempts total, with increasing delays
 * between each. After the fifth attempt fails there are no more retries —
 * the item is marked permanently failed.
 *
 *   Attempt 1: immediate (the first processing attempt itself)
 *   Attempt 2: +15 minutes
 *   Attempt 3: +1 hour
 *   Attempt 4: +6 hours
 *   Attempt 5: +12 hours
 *   Then:      failed
 */
final class RetryPolicy
{
    /** @var array<int,int> attempts-so-far => delay in seconds before the next attempt */
    private const DELAYS_IN_SECONDS = [
        1 => 15 * MINUTE_IN_SECONDS,
        2 => HOUR_IN_SECONDS,
        3 => 6 * HOUR_IN_SECONDS,
        4 => 12 * HOUR_IN_SECONDS,
    ];

    public function shouldRetry(int $attemptsSoFar): bool
    {
        return array_key_exists($attemptsSoFar, self::DELAYS_IN_SECONDS);
    }

    public function nextAttemptAt(int $attemptsSoFar): \DateTimeImmutable
    {
        $delaySeconds = self::DELAYS_IN_SECONDS[$attemptsSoFar] ?? 0;

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('+%d seconds', $delaySeconds));
    }
}
