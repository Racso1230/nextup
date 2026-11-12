<?php

namespace App\Evaluation;

use App\Recommender\RecommendationContext;

/**
 * Time-split backtest scoring (docs/eval/method.md, section 2). The Laravel side builds the library as it was at
 * the cut-off and the set of games played after it; this class only ranks and scores.
 */
final class TimeSplit
{
    public const array CUTOFFS = [5, 10];

    /**
     * @param  list<int>  $relevantEntryIds  games whose playtime grew after the cut-off
     * @return array{ranker: string, relevant: int, ranked: int, precision: array<int, float>, recall: array<int, float>, ndcg10: float}
     */
    public function score(RecommendationContext $asOfCutoff, array $relevantEntryIds, Ranker $ranker): array
    {
        $relevant = array_fill_keys($relevantEntryIds, true);
        $ranking = $ranker->rank($asOfCutoff);

        $precision = [];
        $recall = [];

        foreach (self::CUTOFFS as $k) {
            $precision[$k] = Metrics::precisionAt($ranking, $relevant, $k);
            $recall[$k] = Metrics::recallAt($ranking, $relevant, $k);
        }

        return [
            'ranker' => $ranker->name(),
            'relevant' => count($relevant),
            'ranked' => count($ranking),
            'precision' => $precision,
            'recall' => $recall,
            'ndcg10' => Metrics::ndcgAt($ranking, $relevant, 10),
        ];
    }
}
