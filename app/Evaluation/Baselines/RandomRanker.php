<?php

namespace App\Evaluation\Baselines;

use App\Evaluation\Ranker;
use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;

/**
 * A deterministic shuffle of the candidates. It carries its own small PRNG (xorshift32) instead of using
 * mt_srand()/shuffle(), so the order depends only on the seed and the candidates, never on other code.
 */
final class RandomRanker implements Ranker
{
    public function __construct(private readonly int $seed = 20261112) {}

    public function name(): string
    {
        return 'random';
    }

    public function rank(RecommendationContext $context): array
    {
        $ids = array_map(fn (GameFacts $g): int => $g->entryId, Candidates::select($context));
        // Sort first so the result doesn't depend on the order the library was loaded in.
        sort($ids);

        $state = ($this->seed & 0xFFFFFFFF) ?: 1; // xorshift must never start at zero

        // Fisher-Yates shuffle.
        for ($i = count($ids) - 1; $i > 0; $i--) {
            $state = self::next($state);
            $j = $state % ($i + 1);
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        }

        return array_values($ids);
    }

    private static function next(int $x): int
    {
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= $x >> 17;
        $x ^= ($x << 5) & 0xFFFFFFFF;

        return $x;
    }
}
