<?php

namespace App\Recommender;

use App\Enums\TagType;

/**
 * The fixed numbers in docs/recommender.md, in one place. These are design decisions (approved by Oscar), not
 * tuning knobs: weights are tuned in config/recommender.php, these change only with the spec.
 */
final class Params
{
    /** Engagement without a time to beat saturates at about this many hours (log scale). */
    public const float ENGAGEMENT_LOG_CAP_HOURS = 50.0;

    /** Fewer played games than this scales taste down. */
    public const int TASTE_MIN_PLAYED = 5;

    /** Momentum: this many hours in the last 14 days is full momentum. */
    public const float MOMENTUM_FULL_HOURS = 5.0;

    /** Momentum: recency decay time constant, in days. */
    public const float MOMENTUM_DECAY_DAYS = 14.0;

    /** Fit: remaining hours at which fit is 0.5. */
    public const float FIT_HALF_HOURS = 10.0;

    /** Fit when the time to beat is unknown. Neutral, never zero. */
    public const float FIT_UNKNOWN = 0.5;

    /** Finished: not played for this many days (and past the time to beat). */
    public const int FINISHED_IDLE_DAYS = 30;

    /** Freshness: a "tried once" game is under this many minutes... */
    public const int FRESH_TRIED_MINUTES = 60;

    /** ...and untouched for this many days. */
    public const int FRESH_TRIED_IDLE_DAYS = 180;

    public const float FRESH_NEVER_PLAYED = 1.0;

    public const float FRESH_TRIED_ONCE = 0.5;

    /** Dropped: under this many minutes played... */
    public const int DROPPED_MAX_MINUTES = 120;

    /** ...or under this share of the time to beat... */
    public const float DROPPED_MAX_TTB_SHARE = 0.15;

    /** ...and not played for this many days. */
    public const int DROPPED_IDLE_DAYS = 60;

    /** How much each tag type counts in taste and mismatch vectors. */
    public static function tagTypeWeight(TagType $type): float
    {
        return match ($type) {
            TagType::Genre, TagType::Theme => 1.0,
            TagType::GameMode, TagType::Perspective => 0.5,
            TagType::Keyword => 0.25,
        };
    }
}
