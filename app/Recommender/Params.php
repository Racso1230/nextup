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

    /** Fit: remaining hours at which fit is 0.5, as a multiple of the hours the user has (2h x 5 = v1's 10h). */
    public const float FIT_HALF_HOURS_PER_AVAILABLE_HOUR = 5.0;

    /** Mood for a game with no tags in either mood list. Neutral, so missing data never counts against it. */
    public const float MOOD_UNKNOWN = 0.5;

    /** "Playing it" counts as full momentum for this many days. */
    public const int PLAYING_MOMENTUM_DAYS = 3;

    /** "Not tonight" hides a game for this many hours... */
    public const int NOT_TONIGHT_HIDDEN_HOURS = 24;

    /** ...then the snooze penalty is exp(-(days - 1) / this). */
    public const float SNOOZE_DECAY_DAYS = 2.0;

    /** Social: this many friends who played it recently is full social. */
    public const int SOCIAL_FULL_FRIENDS = 3;

    /** IGDB game modes (ids are stable). */
    public const int MODE_SINGLE_PLAYER = 1;

    /** Modes that mean "you can play this with friends". */
    public const array MODES_WITH_FRIENDS = [2, 3, 4, 5, 6]; // Multiplayer, Co-operative, Split screen, MMO, Battle Royale

    /**
     * Mood lists by IGDB (type, id), checked against the real tag table on 2026-11-12.
     *
     * @return list<TagRef>
     */
    public static function moodTags(Mood $mood): array
    {
        $genre = fn (int $id, string $name): TagRef => new TagRef(TagType::Genre, $id, $name);
        $theme = fn (int $id, string $name): TagRef => new TagRef(TagType::Theme, $id, $name);

        return match ($mood) {
            Mood::Intense => [
                $genre(4, 'Fighting'), $genre(5, 'Shooter'), $genre(10, 'Racing'), $genre(24, 'Tactical'),
                $genre(25, 'Hack and slash/Beat \'em up'), $genre(36, 'MOBA'),
                $theme(1, 'Action'), $theme(19, 'Horror'), $theme(20, 'Thriller'), $theme(21, 'Survival'),
                $theme(39, 'Warfare'),
                new TagRef(TagType::GameMode, 6, 'Battle Royale'),
            ],
            Mood::Chill => [
                $genre(2, 'Point-and-click'), $genre(7, 'Music'), $genre(9, 'Puzzle'), $genre(13, 'Simulator'),
                $genre(15, 'Strategy'), $genre(16, 'Turn-based strategy (TBS)'), $genre(26, 'Quiz/Trivia'),
                $genre(30, 'Pinball'), $genre(34, 'Visual Novel'), $genre(35, 'Card & Board Game'),
                $theme(27, 'Comedy'), $theme(28, 'Business'), $theme(33, 'Sandbox'), $theme(34, 'Educational'),
                $theme(35, 'Kids'), $theme(40, 'Party'), $theme(41, '4X'),
            ],
        };
    }

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
