<?php

namespace App\Recommender;

/**
 * Which games can be recommended: matched games only, one per IGDB game, minus the likely-finished ones.
 */
final class Candidates
{
    /**
     * @return list<GameFacts>
     */
    public static function select(RecommendationContext $context): array
    {
        /** @var array<int, GameFacts> $byIgdb */
        $byIgdb = [];

        foreach ($context->library as $game) {
            if ($game->igdbId === null) {
                continue;
            }

            // Two Steam apps that are one IGDB game become one candidate: keep the one played most
            // (ties go to the lower entry id, so the result doesn't depend on input order).
            $current = $byIgdb[$game->igdbId] ?? null;

            if ($current === null
                || $game->playtimeMinutes > $current->playtimeMinutes
                || ($game->playtimeMinutes === $current->playtimeMinutes && $game->entryId < $current->entryId)) {
                $byIgdb[$game->igdbId] = $game;
            }
        }

        return array_values(array_filter(
            $byIgdb,
            fn (GameFacts $game): bool => ! self::isLikelyFinished($game, $context),
        ));
    }

    /**
     * Played at least the time to beat and not touched for 30+ days. Unknown time to beat never counts as
     * finished, which keeps endless multiplayer games eligible.
     */
    public static function isLikelyFinished(GameFacts $game, RecommendationContext $context): bool
    {
        if ($game->ttbNormallySeconds === null) {
            return false;
        }

        return $game->playtimeMinutes * 60 >= $game->ttbNormallySeconds
            && Days::idleFor($game->lastPlayedAt, $context->now, Params::FINISHED_IDLE_DAYS);
    }
}
