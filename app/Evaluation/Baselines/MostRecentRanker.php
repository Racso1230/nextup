<?php

namespace App\Evaluation\Baselines;

use App\Evaluation\Ranker;
use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;

/** Most recently played first; never-played games (no date) come last. */
final class MostRecentRanker implements Ranker
{
    public function name(): string
    {
        return 'most-recent';
    }

    public function rank(RecommendationContext $context): array
    {
        $games = Candidates::select($context);

        usort($games, function (GameFacts $a, GameFacts $b): int {
            $byDate = ($b->lastPlayedAt?->getTimestamp() ?? PHP_INT_MIN) <=> ($a->lastPlayedAt?->getTimestamp() ?? PHP_INT_MIN);

            return $byDate ?: Tiebreak::compare($a, $b);
        });

        return array_map(fn (GameFacts $g): int => $g->entryId, $games);
    }
}
