<?php

namespace App\Recommender;

use DateTimeImmutable;

/**
 * Per-user facts computed once per run and shared by every signal: the IDF table, the taste and dropped
 * vectors with each game's contribution (for leave-one-out), and the run time.
 */
final readonly class Profile
{
    /**
     * @param  array<string, float>  $idf  tag key => inverse document frequency over the user's tagged library
     * @param  array<string, float>  $tasteVector  tag key => summed engagement-weighted tag weight
     * @param  array<int, array<string, float>>  $tasteContributions  entryId => that game's share of the taste vector
     * @param  array<string, float>  $droppedVector  tag key => summed tag weight of dropped games
     * @param  array<int, array<string, float>>  $droppedContributions  entryId => that game's share of the dropped vector
     * @param  int  $playedCount  played games that contributed to taste
     * @param  array<int, Feedback>  $feedback  entryId => the latest feedback on that game
     */
    public function __construct(
        public array $idf,
        public array $tasteVector,
        public array $tasteContributions,
        public array $droppedVector,
        public array $droppedContributions,
        public int $playedCount,
        public DateTimeImmutable $now,
        public SessionContext $session = new SessionContext,
        public array $feedback = [],
    ) {}

    public function feedbackFor(int $entryId): ?Feedback
    {
        return $this->feedback[$entryId] ?? null;
    }
}
