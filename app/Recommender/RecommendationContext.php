<?php

namespace App\Recommender;

use DateTimeImmutable;

/**
 * The input to one ranking run: a user's whole library (matched or not, played or not), the moment of the run,
 * and the weights. Phase 4 adds session context and feedback here.
 */
final readonly class RecommendationContext
{
    /**
     * @param  list<GameFacts>  $library
     */
    public function __construct(
        public array $library,
        public DateTimeImmutable $now,
        public Weights $weights,
    ) {}
}
