<?php

namespace Tests\Unit\Recommender\Support;

use App\Enums\TagType;
use App\Recommender\GameFacts;
use App\Recommender\Profile;
use App\Recommender\RecommendationContext;
use App\Recommender\TagRef;
use App\Recommender\Weights;
use DateTimeImmutable;

/**
 * Builders for the recommender unit tests. Time is always fixed: "now" is 2026-11-11 12:00 UTC, so a game
 * played `daysAgo(3)` was played at exactly 72 hours before "now" and day counts are whole numbers.
 */
final class Facts
{
    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-11 12:00:00 UTC');
    }

    public static function daysAgo(int $days): DateTimeImmutable
    {
        return self::now()->modify("-{$days} days");
    }

    public static function genre(int $id): TagRef
    {
        return new TagRef(TagType::Genre, $id, "genre {$id}");
    }

    public static function theme(int $id): TagRef
    {
        return new TagRef(TagType::Theme, $id, "theme {$id}");
    }

    public static function mode(int $id): TagRef
    {
        return new TagRef(TagType::GameMode, $id, "mode {$id}");
    }

    public static function perspective(int $id): TagRef
    {
        return new TagRef(TagType::Perspective, $id, "perspective {$id}");
    }

    public static function keyword(int $id): TagRef
    {
        return new TagRef(TagType::Keyword, $id, "keyword {$id}");
    }

    /**
     * One game with sensible defaults: matched (igdbId = 1000 + id), unplayed, no time to beat, no tags.
     *
     * @param  list<TagRef>  $tags
     */
    public static function game(
        int $id,
        int $minutes = 0,
        ?DateTimeImmutable $lastPlayed = null,
        ?float $ttbHours = null,
        array $tags = [],
        ?int $twoWeeks = null,
        ?int $snapshotRecent = null,
        bool $unmatched = false,
        ?int $igdbId = null,
    ): GameFacts {
        return new GameFacts(
            entryId: $id,
            igdbId: $unmatched ? null : ($igdbId ?? 1000 + $id),
            name: "Game {$id}",
            playtimeMinutes: $minutes,
            playtime2WeeksMinutes: $twoWeeks,
            recentMinutesFromSnapshots: $snapshotRecent,
            lastPlayedAt: $lastPlayed,
            ttbNormallySeconds: $ttbHours === null ? null : (int) round($ttbHours * 3600),
            tags: $tags,
        );
    }

    /**
     * @param  list<GameFacts>  $library
     */
    public static function context(array $library): RecommendationContext
    {
        $config = require __DIR__.'/../../../../config/recommender.php';

        return new RecommendationContext($library, self::now(), Weights::fromArray($config['weights']));
    }

    /**
     * A hand-built Profile, for testing a signal without going through ProfileBuilder.
     *
     * @param  array<string, float>  $idf
     * @param  array<string, float>  $taste
     * @param  array<int, array<string, float>>  $tasteContributions
     * @param  array<string, float>  $dropped
     * @param  array<int, array<string, float>>  $droppedContributions
     */
    public static function profile(
        array $idf = [],
        array $taste = [],
        array $tasteContributions = [],
        array $dropped = [],
        array $droppedContributions = [],
        int $playedCount = 0,
    ): Profile {
        return new Profile($idf, $taste, $tasteContributions, $dropped, $droppedContributions, $playedCount, self::now());
    }

    /**
     * Float-tolerant comparison of two sparse vectors: same keys, each value within 1e-6.
     *
     * @param  array<string, float>  $actual
     * @param  array<string, float>  $expected
     */
    public static function assertVector(array $actual, array $expected): void
    {
        ksort($actual);
        ksort($expected);

        expect(array_keys($actual))->toBe(array_keys($expected));

        foreach ($expected as $key => $value) {
            expect($actual[$key])->toEqualWithDelta($value, 1e-6, "vector entry {$key}");
        }
    }
}
