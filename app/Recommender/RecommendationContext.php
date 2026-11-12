<?php

namespace App\Recommender;

use DateTimeImmutable;

/**
 * The input to one ranking run: a user's whole library (matched or not, played or not), the moment of the run,
 * the weights, what the user said about tonight, and their latest feedback per game.
 */
final readonly class RecommendationContext
{
    /**
     * @param  list<GameFacts>  $library
     * @param  array<int, Feedback>  $feedback  entryId => latest feedback
     */
    public function __construct(
        public array $library,
        public DateTimeImmutable $now,
        public Weights $weights,
        public SessionContext $session = new SessionContext,
        public array $feedback = [],
    ) {}
}
