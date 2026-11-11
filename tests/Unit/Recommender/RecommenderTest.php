<?php

use App\Recommender\Recommender;
use App\Recommender\SignalName;
use Tests\Unit\Recommender\Support\Facts;

/**
 * A small library: two heavily played roguelikes (genre 1 + rare keyword 9), a finished story game, an
 * unplayed roguelike, an unplayed sports game, an unmatched tool, and a duplicate Steam app of the roguelike.
 */
function rankingLibrary(): array
{
    return [
        Facts::game(1, minutes: 8400, lastPlayed: Facts::daysAgo(90), ttbHours: 20, tags: [Facts::genre(1), Facts::keyword(9)]),
        Facts::game(2, minutes: 3000, lastPlayed: Facts::daysAgo(2), tags: [Facts::genre(1), Facts::keyword(9)]),
        Facts::game(3, minutes: 900, lastPlayed: Facts::daysAgo(120), ttbHours: 10, tags: [Facts::genre(2)]),
        Facts::game(4, tags: [Facts::genre(1), Facts::keyword(9)], ttbHours: 8),
        Facts::game(5, tags: [Facts::genre(3)]),
        Facts::game(6, minutes: 30, unmatched: true),
        Facts::game(7, tags: [Facts::genre(1), Facts::keyword(9)], igdbId: 1004),
    ];
}

it('ranks matched, unfinished candidates best first with one contribution per signal', function () {
    $ranked = (new Recommender)->rank(Facts::context(rankingLibrary()));
    $ids = array_map(fn ($r) => $r->game->entryId, $ranked);

    // 3 is finished (past ttb, idle 120 days); 1 is also past ttb and idle 90 days; 6 is unmatched;
    // 7 collapses into 4 (same igdb id, 4 wins on the lower entry id).
    expect($ids)->not->toContain(3)->not->toContain(1)->not->toContain(6)->not->toContain(7)
        ->and($ids)->toContain(2)->toContain(4)->toContain(5)
        ->and($ranked[0]->contributions)->toHaveCount(count(SignalName::cases()));

    $scores = array_map(fn ($r) => $r->score, $ranked);
    $sorted = $scores;
    rsort($sorted);
    expect($scores)->toBe($sorted);
});

it('puts the unplayed game that matches the user taste above the one that does not', function () {
    $ranked = (new Recommender)->rank(Facts::context(rankingLibrary()));
    $position = array_flip(array_map(fn ($r) => $r->game->entryId, $ranked));

    expect($position[4])->toBeLessThan($position[5]);
});

it('sums the weighted contributions into the score, subtracting mismatch', function () {
    foreach ((new Recommender)->rank(Facts::context(rankingLibrary())) as $ranked) {
        $sum = array_sum(array_map(fn ($c) => $c->weighted, $ranked->contributions));

        expect($ranked->score)->toEqualWithDelta($sum, 1e-9);
    }
});

it('names the played games and shared tags behind a taste match', function () {
    $ranked = (new Recommender)->rank(Facts::context(rankingLibrary()));
    $pick = array_values(array_filter($ranked, fn ($r) => $r->game->entryId === 4))[0];

    expect(array_column($pick->evidenceGames, 'name'))->toBe(['Game 1', 'Game 2'])
        ->and($pick->evidenceGames[0]['hours'])->toEqualWithDelta(140.0, 1e-9)
        ->and($pick->evidenceTags)->toContain('genre 1')->toContain('keyword 9');
});

it('is deterministic whatever the input order', function () {
    $library = rankingLibrary();
    $first = array_map(fn ($r) => $r->game->entryId, (new Recommender)->rank(Facts::context($library)));
    $second = array_map(fn ($r) => $r->game->entryId, (new Recommender)->rank(Facts::context(array_reverse($library))));

    expect($second)->toBe($first);
});

it('returns nothing for an empty library', function () {
    expect((new Recommender)->rank(Facts::context([])))->toBe([]);
});
