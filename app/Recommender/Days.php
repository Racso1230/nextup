<?php

namespace App\Recommender;

use DateTimeImmutable;

/**
 * Whole-day arithmetic for idle thresholds. Days are floored, and every threshold in the spec is inclusive
 * ("30 days or more").
 */
final class Days
{
    /** Whole days between then and now, or null when there is no date (Steam reports 0 for very old play). */
    public static function since(?DateTimeImmutable $then, DateTimeImmutable $now): ?int
    {
        if ($then === null) {
            return null;
        }

        return max(0, intdiv($now->getTimestamp() - $then->getTimestamp(), 86400));
    }

    /** True when at least $days have passed. A missing date counts as idle for ever. */
    public static function idleFor(?DateTimeImmutable $then, DateTimeImmutable $now, int $days): bool
    {
        $since = self::since($then, $now);

        return $since === null || $since >= $days;
    }
}
