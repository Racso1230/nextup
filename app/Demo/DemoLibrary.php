<?php

namespace App\Demo;

use App\Enums\TagType;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;
use App\Recommender\TagRef;
use App\Recommender\Weights;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * The demo catalogue (public IGDB metadata for 127 real games, exported once from the dev database into
 * database/data/demo-catalogue.json, no user data) and the persona's library built from it.
 *
 * `context()` needs no database: the landing page hero and the seeded demo accounts rank the same
 * library. The entry id of game N (0-based) is N + 1 here, a stable stand-in for library_entries.id.
 *
 * @phpstan-type CatalogueTag array{type: string, igdb_id: int, name: string}
 * @phpstan-type CatalogueGame array{
 *     steam_appid: int,
 *     name: string,
 *     icon_hash: string|null,
 *     igdb_id: int,
 *     slug: string|null,
 *     first_release_date: string|null,
 *     cover_image_id: string|null,
 *     ttb_hastily: int|null,
 *     ttb_normally: int|null,
 *     ttb_completely: int|null,
 *     similar_igdb_ids: list<int>|null,
 *     tags: list<CatalogueTag>
 * }
 */
final class DemoLibrary
{
    /** @var list<CatalogueGame>|null */
    private static ?array $catalogue = null;

    /**
     * @return list<CatalogueGame>
     */
    public static function catalogue(): array
    {
        if (self::$catalogue !== null) {
            return self::$catalogue;
        }

        $json = file_get_contents(database_path('data/demo-catalogue.json'));

        if ($json === false) {
            throw new RuntimeException('The demo catalogue is missing.');
        }

        /** @var list<CatalogueGame> $games */
        $games = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return self::$catalogue = $games;
    }

    /**
     * The persona's whole library as the recommender sees it, at the given moment.
     */
    public static function context(CarbonImmutable $now): RecommendationContext
    {
        $friends = DemoPersona::friendActivity();
        $library = [];

        foreach (self::catalogue() as $index => $game) {
            $play = DemoPersona::play($game['steam_appid'], $now);

            $library[] = new GameFacts(
                entryId: $index + 1,
                igdbId: $game['igdb_id'],
                name: $game['name'],
                playtimeMinutes: $play->minutes ?? 0,
                playtime2WeeksMinutes: $play?->minutes2Weeks,
                // Seeded accounts have one snapshot, so the window isn't covered and this stays null there too.
                recentMinutesFromSnapshots: null,
                lastPlayedAt: $play?->lastPlayedAt->toDateTimeImmutable(),
                ttbNormallySeconds: $game['ttb_normally'],
                tags: array_map(
                    fn (array $tag): TagRef => new TagRef(TagType::from($tag['type']), $tag['igdb_id'], $tag['name']),
                    $game['tags'],
                ),
                // The friend rows always exist for the demo, so a game nobody played is a real 0.
                friendsPlayedRecently: $friends[$game['steam_appid']] ?? 0,
            );
        }

        return new RecommendationContext(
            library: $library,
            now: $now->toDateTimeImmutable(),
            weights: Weights::fromArray(config()->array('recommender.weights')),
        );
    }
}
