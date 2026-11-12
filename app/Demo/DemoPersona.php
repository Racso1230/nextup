<?php

namespace App\Demo;

use Carbon\CarbonImmutable;

/**
 * The invented player behind every demo library, so the demo tells one clear story.
 *
 * "Demo player" loves story-rich RPGs and immersive sims (Skyrim, Fallout, Baldur's Gate 3, BioShock,
 * Dishonored, Prey), and plays a couple of co-op shooters with friends (Left 4 Dead 2, Borderlands 2).
 * Everything else in the catalogue is unplayed, which is what the recommendations choose from.
 *
 * - Loved: 14 games with 22 to 240 hours, last played 2 to 11 months ago. All are past their time to
 *   beat, so they count as finished and are never suggested again.
 * - Dropped: 4 games under 2 hours, last touched 7 to 12 months ago. They surface as "you tried it once".
 * - Recent: 3 games played in the last week, with playtime in the last two weeks. Momentum favours these.
 * - Unplayed: the rest of the catalogue.
 * - Friends: three multiplayer games a few friends played recently, so the friends signal shows.
 *
 * The games are picked by Steam appid, from their IGDB tags (RPG and stealth/immersive-sim for the
 * loved ones, co-op shooters for the friends games). A fixed list keeps the story stable, and there is
 * no randomness anywhere: the same `$now` always yields the same library. Playtime is invented; the
 * games and their tags are real.
 */
final class DemoPersona
{
    /**
     * Loved games: appid => [lifetime hours, days since last played].
     *
     * @var array<int, array{int, int}>
     */
    private const array LOVED = [
        489830 => [240, 95],   // Skyrim Special Edition
        22380 => [120, 150],   // Fallout: New Vegas
        1086940 => [150, 120], // Baldur's Gate 3
        377160 => [160, 200],  // Fallout 4
        22330 => [85, 330],    // Oblivion GOTY
        379430 => [120, 240],  // Kingdom Come: Deliverance
        1245620 => [140, 180], // Elden Ring
        7670 => [22, 300],     // BioShock
        205100 => [32, 270],   // Dishonored
        403640 => [41, 250],   // Dishonored 2
        480490 => [36, 210],   // Prey
        550 => [70, 60],       // Left 4 Dead 2, with friends
        49520 => [90, 110],    // Borderlands 2, with friends
        22370 => [70, 280],    // Fallout 3
    ];

    /**
     * Dropped games: appid => [lifetime minutes, days since last played].
     *
     * @var array<int, array{int, int}>
     */
    private const array DROPPED = [
        1158310 => [84, 220],  // Crusader Kings III
        1716740 => [108, 260], // Starfield
        286690 => [48, 330],   // Metro 2033 Redux
        1148590 => [66, 380],  // DOOM 64
    ];

    /**
     * Recent games: appid => [lifetime minutes, minutes in the last two weeks, days since last played].
     *
     * @var array<int, array{int, int, int}>
     */
    private const array RECENT = [
        1903340 => [1260, 360, 1], // Clair Obscur: Expedition 33
        2677660 => [540, 240, 3],  // Indiana Jones and the Great Circle
        20920 => [840, 150, 6],    // The Witcher 2
    ];

    /**
     * Friends who played a game in the last two weeks: appid => count.
     *
     * @var array<int, int>
     */
    private const array FRIENDS = [
        552500 => 3,  // Warhammer: Vermintide 2
        976730 => 2,  // Halo: The Master Chief Collection
        500 => 2,     // Left 4 Dead
    ];

    /** The invented playtime for a game, or null when this player never started it. */
    public static function play(int $appId, CarbonImmutable $now): ?DemoPlay
    {
        if (isset(self::LOVED[$appId])) {
            [$hours, $daysAgo] = self::LOVED[$appId];

            return new DemoPlay($hours * 60, null, self::daysAgo($now, $daysAgo));
        }

        if (isset(self::DROPPED[$appId])) {
            [$minutes, $daysAgo] = self::DROPPED[$appId];

            return new DemoPlay($minutes, null, self::daysAgo($now, $daysAgo));
        }

        if (isset(self::RECENT[$appId])) {
            [$minutes, $twoWeeks, $daysAgo] = self::RECENT[$appId];

            return new DemoPlay($minutes, $twoWeeks, self::daysAgo($now, $daysAgo));
        }

        return null;
    }

    /**
     * How the persona treats a game: 'loved', 'dropped', 'recent' or 'unplayed'.
     */
    public static function kind(int $appId): string
    {
        return match (true) {
            isset(self::LOVED[$appId]) => 'loved',
            isset(self::DROPPED[$appId]) => 'dropped',
            isset(self::RECENT[$appId]) => 'recent',
            default => 'unplayed',
        };
    }

    /**
     * Invented friend activity: steam appid => friends who played it in the last two weeks.
     *
     * @return array<int, int>
     */
    public static function friendActivity(): array
    {
        return self::FRIENDS;
    }

    /** Evening of the given day, so the time of day is fixed and the result is stable within a day. */
    private static function daysAgo(CarbonImmutable $now, int $days): CarbonImmutable
    {
        return $now->utc()->subDays($days)->setTime(20, 30);
    }
}
