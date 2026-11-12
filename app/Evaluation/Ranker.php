<?php

namespace App\Evaluation;

use App\Recommender\RecommendationContext;

/**
 * Anything that orders a user's candidates: the recommender itself, or a baseline to compare it with.
 */
interface Ranker
{
    /** A short label for result tables, e.g. "recommender", "random". */
    public function name(): string;

    /**
     * Library entry ids, best first. A ranker may return any subset of the library; the harness keeps only the
     * ids in its candidate pool, in the order given.
     *
     * @return list<int>
     */
    public function rank(RecommendationContext $context): array;
}
