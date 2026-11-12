<?php

namespace App\Evaluation;

/**
 * Ranking metrics (docs/eval/method.md). Ranks are 1-based.
 */
final class Metrics
{
    /** NDCG for a single relevant item at a 1-based rank: 1 / log2(rank + 1) inside the cut-off, else 0. */
    public static function ndcgSingle(int $rank, int $k): float
    {
        return $rank <= $k ? 1 / log($rank + 1, 2) : 0.0;
    }

    /**
     * @param  list<int>  $ranked  ids, best first
     * @param  array<int, true>  $relevant  set of relevant ids
     */
    public static function precisionAt(array $ranked, array $relevant, int $k): float
    {
        return self::hitsInTop($ranked, $relevant, $k) / $k;
    }

    /**
     * @param  list<int>  $ranked
     * @param  array<int, true>  $relevant
     */
    public static function recallAt(array $ranked, array $relevant, int $k): float
    {
        return $relevant === [] ? 0.0 : self::hitsInTop($ranked, $relevant, $k) / count($relevant);
    }

    /**
     * NDCG@k with binary relevance: DCG of the ranking over the DCG of a perfect one.
     *
     * @param  list<int>  $ranked
     * @param  array<int, true>  $relevant
     */
    public static function ndcgAt(array $ranked, array $relevant, int $k): float
    {
        if ($relevant === []) {
            return 0.0;
        }

        $dcg = 0.0;

        foreach (array_slice($ranked, 0, $k) as $i => $id) {
            if (isset($relevant[$id])) {
                $dcg += 1 / log($i + 2, 2);
            }
        }

        $ideal = 0.0;

        for ($i = 0; $i < min($k, count($relevant)); $i++) {
            $ideal += 1 / log($i + 2, 2);
        }

        return $dcg / $ideal;
    }

    /**
     * @param  list<int>  $ranked
     * @param  array<int, true>  $relevant
     */
    private static function hitsInTop(array $ranked, array $relevant, int $k): int
    {
        $hits = 0;

        foreach (array_slice($ranked, 0, $k) as $id) {
            if (isset($relevant[$id])) {
                $hits++;
            }
        }

        return $hits;
    }
}
