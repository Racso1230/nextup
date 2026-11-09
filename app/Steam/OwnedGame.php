<?php

namespace App\Steam;

use Carbon\CarbonImmutable;

final readonly class OwnedGame
{
    public function __construct(
        public int $appId,
        public string $name,
        public int $playtimeMinutes,
        public ?int $playtime2WeeksMinutes,
        public ?CarbonImmutable $lastPlayedAt,
        public ?string $iconHash,
    ) {}

    /**
     * Returns null when the entry has no valid appid. A missing name falls back to "App <id>".
     * Playtime stays in minutes, as Steam sends it.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): ?self
    {
        $appId = Narrow::int($data, 'appid');

        if ($appId === null || $appId <= 0) {
            return null;
        }

        $lastPlayed = Narrow::int($data, 'rtime_last_played');
        $twoWeeks = Narrow::int($data, 'playtime_2weeks');

        return new self(
            appId: $appId,
            name: Narrow::nonEmptyString($data, 'name') ?? "App {$appId}",
            playtimeMinutes: max(0, Narrow::int($data, 'playtime_forever') ?? 0),
            playtime2WeeksMinutes: $twoWeeks === null ? null : max(0, $twoWeeks),
            lastPlayedAt: $lastPlayed !== null && $lastPlayed > 0
                ? CarbonImmutable::createFromTimestampUTC($lastPlayed)
                : null,
            iconHash: Narrow::nonEmptyString($data, 'img_icon_url'),
        );
    }
}
