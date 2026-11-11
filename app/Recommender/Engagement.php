<?php

namespace App\Recommender;

/**
 * How strongly a played game counts as evidence of taste, in 0..1 (docs/recommender.md, "Engagement").
 */
final class Engagement
{
    public static function of(GameFacts $game): float
    {
        if (! $game->isPlayed()) {
            return 0.0;
        }

        if ($game->ttbNormallySeconds !== null && $game->ttbNormallySeconds > 0) {
            // Relative to the time to beat, so 40 hours on a 10-hour game saturates.
            return min(1.0, ($game->playtimeMinutes * 60) / $game->ttbNormallySeconds);
        }

        // No time to beat: log-scaled hours, saturating at about 50 hours.
        return min(1.0, log(1 + $game->playtimeHours()) / log(1 + Params::ENGAGEMENT_LOG_CAP_HOURS));
    }
}
