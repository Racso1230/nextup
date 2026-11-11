<?php

namespace App\Recommender;

use DateTimeImmutable;

/**
 * Everything the recommender knows about one game in one user's library. Built on the Laravel side from
 * library_entries, games, tags and playtime_snapshots, so the scoring code never touches the database.
 */
final readonly class GameFacts
{
    /**
     * @param  int  $entryId  library_entries.id (identifies the pick when storing results)
     * @param  ?int  $igdbId  null for unmatched games, which are never candidates
     * @param  ?int  $playtime2WeeksMinutes  Steam's playtime_2weeks; null when Steam omits it
     * @param  ?int  $recentMinutesFromSnapshots  minutes played in the last 14 days according to snapshots, or null
     *                                            when the snapshots don't cover that whole window yet
     * @param  ?int  $ttbNormallySeconds  IGDB time to beat; null means unknown, never zero
     * @param  list<TagRef>  $tags
     */
    public function __construct(
        public int $entryId,
        public ?int $igdbId,
        public string $name,
        public int $playtimeMinutes,
        public ?int $playtime2WeeksMinutes,
        public ?int $recentMinutesFromSnapshots,
        public ?DateTimeImmutable $lastPlayedAt,
        public ?int $ttbNormallySeconds,
        public array $tags,
    ) {}

    public function isPlayed(): bool
    {
        return $this->playtimeMinutes > 0;
    }

    public function playtimeHours(): float
    {
        return $this->playtimeMinutes / 60;
    }
}
