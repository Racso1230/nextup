<?php

namespace App\Steam;

final readonly class OwnedGamesResult
{
    /** @param  list<OwnedGame>  $games */
    private function __construct(
        public bool $isPrivate,
        public array $games,
    ) {}

    /** "Game details" are not public: Steam sends an empty `response` object. */
    public static function private(): self
    {
        return new self(true, []);
    }

    /**
     * A public library, possibly with zero games.
     *
     * @param  list<OwnedGame>  $games
     */
    public static function withGames(array $games): self
    {
        return new self(false, $games);
    }

    /**
     * Parses a decoded GetOwnedGames body. An empty or odd `response` is private, never "zero games".
     * Only an explicit `game_count` of 0 or a `games` list marks a library as public.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $response = $data['response'] ?? null;

        if (! is_array($response) || $response === []) {
            return self::private();
        }

        $rawGames = $response['games'] ?? null;

        if (! is_array($rawGames)) {
            return ($response['game_count'] ?? null) === 0 ? self::withGames([]) : self::private();
        }

        $games = [];

        foreach ($rawGames as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $game = OwnedGame::fromArray($raw);

            // The list can repeat an appid. Keep the first.
            if ($game !== null && ! isset($games[$game->appId])) {
                $games[$game->appId] = $game;
            }
        }

        return self::withGames(array_values($games));
    }
}
