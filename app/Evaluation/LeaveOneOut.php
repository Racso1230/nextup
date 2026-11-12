<?php

namespace App\Evaluation;

use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;

/**
 * Leave-one-out evaluation (docs/eval/method.md, section 1): pretend each loved game was never played, and see
 * where a ranker puts it among the games the user really hasn't started.
 */
final class LeaveOneOut
{
    /** A game counts as "loved" from this many minutes played (10 hours). */
    public const int MIN_MINUTES = 600;

    public function run(RecommendationContext $context, Ranker $ranker): LeaveOneOutScore
    {
        $unplayedPool = [];

        foreach ($context->library as $game) {
            if ($game->igdbId !== null && ! $game->isPlayed()) {
                $unplayedPool[$game->entryId] = true;
            }
        }

        $trials = [];

        foreach (self::heldOut($context) as $held) {
            $trialContext = new RecommendationContext(
                array_map(fn (GameFacts $g): GameFacts => $g->entryId === $held->entryId ? self::asUnplayed($g) : $g, $context->library),
                $context->now,
                $context->weights,
            );

            $pool = $unplayedPool + [$held->entryId => true];
            $ranking = array_values(array_filter($ranker->rank($trialContext), fn (int $id): bool => isset($pool[$id])));
            $position = array_search($held->entryId, $ranking, true);

            // The held-out game can disappear when a duplicate Steam app of the same IGDB game wins the
            // collapse. There is nothing to measure then, so the trial is skipped.
            if ($position === false) {
                continue;
            }

            $trials[] = ['entry_id' => $held->entryId, 'name' => $held->name, 'rank' => $position + 1, 'pool' => count($ranking)];
        }

        return new LeaveOneOutScore($ranker->name(), $trials);
    }

    /**
     * Matched, tagged games with at least 10 hours played.
     *
     * @return list<GameFacts>
     */
    public static function heldOut(RecommendationContext $context): array
    {
        return array_values(array_filter(
            $context->library,
            fn (GameFacts $g): bool => $g->igdbId !== null && $g->tags !== [] && $g->playtimeMinutes >= self::MIN_MINUTES,
        ));
    }

    /** The same game as if the user had never launched it. */
    public static function asUnplayed(GameFacts $game): GameFacts
    {
        return new GameFacts(
            entryId: $game->entryId,
            igdbId: $game->igdbId,
            name: $game->name,
            playtimeMinutes: 0,
            playtime2WeeksMinutes: null,
            recentMinutesFromSnapshots: null,
            lastPlayedAt: null,
            ttbNormallySeconds: $game->ttbNormallySeconds,
            tags: $game->tags,
            friendsPlayedRecently: $game->friendsPlayedRecently,
        );
    }
}
