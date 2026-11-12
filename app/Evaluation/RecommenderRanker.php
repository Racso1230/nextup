<?php

namespace App\Evaluation;

use App\Recommender\RecommendationContext;
use App\Recommender\Recommender;

/** The real recommender, behind the Ranker interface so it can be compared with baselines. */
final class RecommenderRanker implements Ranker
{
    public function __construct(private readonly Recommender $recommender = new Recommender) {}

    public function name(): string
    {
        return 'recommender';
    }

    public function rank(RecommendationContext $context): array
    {
        return array_map(fn ($ranked): int => $ranked->game->entryId, $this->recommender->rank($context));
    }
}
