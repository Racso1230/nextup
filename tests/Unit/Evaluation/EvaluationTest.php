<?php

use App\Evaluation\LeaveOneOut;
use App\Evaluation\LeaveOneOutScore;
use App\Evaluation\Metrics;
use App\Evaluation\Ranker;
use App\Evaluation\RecommenderRanker;
use App\Evaluation\TimeSplit;
use App\Recommender\RecommendationContext;
use Tests\Unit\Recommender\Support\Facts;

arch('the evaluation harness has no Laravel dependencies')
    ->expect('App\Evaluation')
    ->not->toUse(['Illuminate', 'Laravel']);

/** A ranker that returns a fixed order, for testing the harness in isolation. */
function fixedRanker(array $order, string $name = 'fixed'): Ranker
{
    return new class($order, $name) implements Ranker
    {
        public function __construct(private array $order, private string $label) {}

        public function name(): string
        {
            return $this->label;
        }

        public function rank(RecommendationContext $context): array
        {
            return $this->order;
        }
    };
}

/*
| Metrics
*/

it('computes single-item NDCG', function () {
    expect(Metrics::ndcgSingle(1, 10))->toBe(1.0)
        ->and(Metrics::ndcgSingle(3, 10))->toEqualWithDelta(0.5, 1e-9)   // 1 / log2(4)
        ->and(Metrics::ndcgSingle(11, 10))->toBe(0.0);
});

it('computes precision, recall and NDCG against a relevant set', function () {
    $ranked = [1, 2, 3, 4, 5, 6];
    $relevant = [2 => true, 5 => true, 9 => true];

    // Top 5 holds 2 and 5: precision 2/5, recall 2/3.
    // DCG = 1/log2(3) + 1/log2(6) = 0.6309298 + 0.3868528 = 1.0177826
    // IDCG (3 relevant) = 1 + 0.6309298 + 0.5 = 2.1309298 -> NDCG 0.477624
    expect(Metrics::precisionAt($ranked, $relevant, 5))->toEqualWithDelta(0.4, 1e-9)
        ->and(Metrics::recallAt($ranked, $relevant, 5))->toEqualWithDelta(2 / 3, 1e-9)
        ->and(Metrics::ndcgAt($ranked, $relevant, 10))->toEqualWithDelta(0.477624, 1e-6)
        ->and(Metrics::recallAt($ranked, [], 5))->toBe(0.0)
        ->and(Metrics::ndcgAt($ranked, [], 10))->toBe(0.0);
});

it('averages leave-one-out trials', function () {
    $score = new LeaveOneOutScore('x', [
        ['entry_id' => 1, 'name' => 'A', 'rank' => 1, 'pool' => 20],
        ['entry_id' => 2, 'name' => 'B', 'rank' => 4, 'pool' => 20],
        ['entry_id' => 3, 'name' => 'C', 'rank' => 20, 'pool' => 20],
    ]);

    expect($score->count())->toBe(3)
        ->and($score->hitRate(5))->toEqualWithDelta(2 / 3, 1e-9)
        ->and($score->precision(5))->toEqualWithDelta(2 / 15, 1e-9)
        ->and($score->mrr())->toEqualWithDelta((1 + 0.25 + 0.05) / 3, 1e-9)
        ->and($score->meanRank())->toEqualWithDelta(25 / 3, 1e-9)
        ->and($score->ndcg(10))->toEqualWithDelta((1 + 1 / log(5, 2)) / 3, 1e-9);
});

/*
| Leave-one-out harness
*/

it('holds out only matched, tagged games with 10+ hours', function () {
    $context = Facts::context([
        Facts::game(1, minutes: 600, tags: [Facts::genre(1)]),
        Facts::game(2, minutes: 599, tags: [Facts::genre(1)]),
        Facts::game(3, minutes: 900, tags: []),
        Facts::game(4, minutes: 900, tags: [Facts::genre(1)], unmatched: true),
    ]);

    expect(array_map(fn ($g) => $g->entryId, LeaveOneOut::heldOut($context)))->toBe([1]);
});

it('ranks each held-out game against the never-played games only', function () {
    // 1 and 2 are loved; 3 and 4 never played; 5 is played but under 10 hours, so never in the pool.
    $context = Facts::context([
        Facts::game(1, minutes: 900, tags: [Facts::genre(1)]),
        Facts::game(2, minutes: 900, tags: [Facts::genre(2)]),
        Facts::game(3, tags: [Facts::genre(1)]),
        Facts::game(4, tags: [Facts::genre(2)]),
        Facts::game(5, minutes: 30, tags: [Facts::genre(1)]),
    ]);

    $score = (new LeaveOneOut)->run($context, fixedRanker([5, 3, 1, 2, 4]));

    // Trial for 1: pool {1, 3, 4} filtered from [5, 3, 1, 2, 4] -> [3, 1, 4]: rank 2 of 3.
    // Trial for 2: pool {2, 3, 4} -> [3, 2, 4]: rank 2 of 3.
    expect(array_column($score->trials, 'rank', 'entry_id'))->toBe([1 => 2, 2 => 2])
        ->and(array_column($score->trials, 'pool'))->toBe([3, 3]);
});

it('really hides the held-out game from the recommender', function () {
    // Game 1 is the only game with genre 9. If it still counted as played, its own tags would make it match
    // itself; held out, the taste comes only from game 2 (genre 1), so the genre-1 lookalike ranks above it.
    $context = Facts::context([
        Facts::game(1, minutes: 6000, lastPlayed: Facts::daysAgo(1), tags: [Facts::genre(9)]),
        Facts::game(2, minutes: 6000, lastPlayed: Facts::daysAgo(1), tags: [Facts::genre(1)]),
        Facts::game(3, tags: [Facts::genre(1)]),
    ]);

    $trial = collect((new LeaveOneOut)->run($context, new RecommenderRanker)->trials)->firstWhere('entry_id', 1);

    expect($trial['rank'])->toBe(2)->and($trial['pool'])->toBe(2);
});

it('resets a held-out game to never played but keeps its tags and length', function () {
    $game = Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(2), ttbHours: 12, tags: [Facts::genre(1)], twoWeeks: 120);
    $reset = LeaveOneOut::asUnplayed($game);

    expect($reset->playtimeMinutes)->toBe(0)
        ->and($reset->lastPlayedAt)->toBeNull()
        ->and($reset->playtime2WeeksMinutes)->toBeNull()
        ->and($reset->ttbNormallySeconds)->toBe($game->ttbNormallySeconds)
        ->and($reset->tags)->toBe($game->tags);
});

/*
| Time split
*/

it('scores a ranking against the games played after the cut-off', function () {
    $result = (new TimeSplit)->score(Facts::context([]), [2, 5, 9], fixedRanker([1, 2, 3, 4, 5, 6], 'fixed'));

    expect($result['ranker'])->toBe('fixed')
        ->and($result['relevant'])->toBe(3)
        ->and($result['precision'][5])->toEqualWithDelta(0.4, 1e-9)
        ->and($result['recall'][5])->toEqualWithDelta(2 / 3, 1e-9)
        ->and($result['ndcg10'])->toEqualWithDelta(0.477624, 1e-6);
});
