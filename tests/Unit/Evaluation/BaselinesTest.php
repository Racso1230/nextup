<?php

use App\Evaluation\Baselines\MostHoursRanker;
use App\Evaluation\Baselines\MostRecentRanker;
use App\Evaluation\Baselines\RandomRanker;
use App\Evaluation\Baselines\TagOverlapRanker;
use App\Evaluation\Ranker;
use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use Tests\Unit\Recommender\Support\Facts;

/** @return list<GameFacts> */
function baselineLibrary(): array
{
    return array_map(fn (int $i): GameFacts => Facts::game($i, minutes: $i % 3 === 0 ? 0 : $i * 10), range(1, 20));
}

it('names the baselines', function () {
    expect((new RandomRanker)->name())->toBe('random')
        ->and((new MostHoursRanker)->name())->toBe('most-hours')
        ->and((new MostRecentRanker)->name())->toBe('most-recent')
        ->and((new TagOverlapRanker)->name())->toBe('tag-overlap');
});

it('shuffles deterministically per seed', function () {
    $context = Facts::context(baselineLibrary());

    $a = (new RandomRanker(1))->rank($context);
    $b = (new RandomRanker(1))->rank($context);
    $c = (new RandomRanker(2))->rank($context);

    expect($a)->toBe($b)
        ->and($a)->not->toBe($c)
        ->and($a)->not->toBe(range(1, 20))
        ->and((new RandomRanker)->rank($context))->toBe((new RandomRanker(20261112))->rank($context));
});

it('does not let the library order change the random shuffle', function () {
    $library = baselineLibrary();

    expect((new RandomRanker)->rank(Facts::context(array_reverse($library))))
        ->toBe((new RandomRanker)->rank(Facts::context($library)));
});

it('orders by hours, with name then entry id breaking ties', function () {
    $context = Facts::context([
        Facts::game(1, minutes: 100),
        Facts::game(2, minutes: 500),
        new GameFacts(3, 1003, 'Aardvark', 100, null, null, null, null, []),
        new GameFacts(4, 1004, 'Aardvark', 100, null, null, null, null, []),
        Facts::game(5, minutes: 0),
    ]);

    // 500 first; then the three at 100: "Aardvark" (3, 4 by id) before "Game 1"; 0 last.
    expect((new MostHoursRanker)->rank($context))->toBe([2, 3, 4, 1, 5]);
});

it('orders by last played, nulls last, with the same tie-break', function () {
    $context = Facts::context([
        Facts::game(1, lastPlayed: Facts::daysAgo(10)),
        Facts::game(2, lastPlayed: Facts::daysAgo(2)),
        Facts::game(3),
        Facts::game(4, lastPlayed: Facts::daysAgo(10)),
        Facts::game(5),
    ]);

    expect((new MostRecentRanker)->rank($context))->toBe([2, 1, 4, 3, 5]);
});

it('ranks by unweighted tag overlap with played games', function () {
    $context = Facts::context([
        Facts::game(1, minutes: 600, tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(2, tags: [Facts::genre(1), Facts::genre(2), Facts::genre(9)]), // 2 shared
        Facts::game(3, tags: [Facts::genre(1)]),                                    // 1 shared
        Facts::game(4, tags: [Facts::genre(9)]),                                    // none
    ]);

    // Game 1 is played, so its own tags don't count for it: 0 overlap, tied with game 4 (name order).
    expect((new TagOverlapRanker)->rank($context))->toBe([2, 3, 1, 4]);
});

it('does not let a played candidate match its own tags', function () {
    $context = Facts::context([
        // The only played game: with itself removed there is nothing to overlap with.
        Facts::game(1, minutes: 600, tags: [Facts::genre(1), Facts::genre(2), Facts::genre(3)]),
        Facts::game(2, tags: [Facts::genre(1)]),
    ]);

    // Game 2 overlaps 1 tag with the played game; game 1 overlaps 0 (it would be 3 without leave-one-out).
    expect((new TagOverlapRanker)->rank($context))->toBe([2, 1]);
});

it('ranks exactly the recommender candidates', function (Ranker $ranker) {
    $library = [
        Facts::game(1, minutes: 600, ttbHours: 5, lastPlayed: Facts::daysAgo(60), tags: [Facts::genre(1)]), // finished
        Facts::game(2, minutes: 120, tags: [Facts::genre(1)]),
        Facts::game(3, unmatched: true),
        Facts::game(4, minutes: 50, igdbId: 1002),                                                            // duplicate of 2
        Facts::game(5),
        Facts::game(6, minutes: 30, lastPlayed: Facts::daysAgo(1)),
    ];
    $context = Facts::context($library);

    $expected = array_map(fn (GameFacts $g): int => $g->entryId, Candidates::select($context));
    $ranked = $ranker->rank($context);

    expect($ranked)->toHaveCount(count($expected))
        ->and(array_values(array_unique($ranked)))->toHaveCount(count($expected));

    sort($ranked);
    sort($expected);

    expect($ranked)->toBe($expected)
        ->and($ranked)->not->toContain(1)
        ->and($ranked)->not->toContain(3);
})->with([
    'random' => fn () => new RandomRanker,
    'most-hours' => fn () => new MostHoursRanker,
    'most-recent' => fn () => new MostRecentRanker,
    'tag-overlap' => fn () => new TagOverlapRanker,
]);
