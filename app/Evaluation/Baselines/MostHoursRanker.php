<?php

namespace App\Evaluation\Baselines;

use App\Evaluation\Ranker;
use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;

/** Most playtime first: the "just show me what I play most" answer. */
final class MostHoursRanker implements Ranker
{
    public function name(): string
    {
        return 'most-hours';
    }

    public function rank(RecommendationContext $context): array
    {
        $games = Candidates::select($context);

        usort($games, fn (GameFacts $a, GameFacts $b): int => ($b->playtimeMinutes <=> $a->playtimeMinutes) ?: Tiebreak::compare($a, $b));

        return array_map(fn (GameFacts $g): int => $g->entryId, $games);
    }
}
