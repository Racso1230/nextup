<?php

use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use Tests\Unit\Recommender\Support\Facts;

/**
 * @param  list<GameFacts>  $library
 * @return list<int> the selected entry ids, sorted, so tests don't depend on output order
 */
function selectedIds(array $library): array
{
    $ids = array_map(fn (GameFacts $game) => $game->entryId, Candidates::select(Facts::context($library)));
    sort($ids);

    return $ids;
}

it('excludes games that are not matched to IGDB', function () {
    $library = [
        Facts::game(1),
        Facts::game(2, unmatched: true),
    ];

    expect(selectedIds($library))->toBe([1]);
});

it('collapses entries that share an IGDB id, keeping the one with the most playtime', function () {
    $library = [
        Facts::game(1, minutes: 30, igdbId: 500),
        Facts::game(2, minutes: 90, igdbId: 500),
        Facts::game(3, minutes: 10, igdbId: 501),
    ];

    expect(selectedIds($library))->toBe([2, 3]);
});

it('collapses regardless of the order the library lists them in', function () {
    $a = Facts::game(1, minutes: 30, igdbId: 500);
    $b = Facts::game(2, minutes: 90, igdbId: 500);

    expect(selectedIds([$a, $b]))->toBe([2])
        ->and(selectedIds([$b, $a]))->toBe([2]);
});

it('returns nothing for an empty library', function () {
    expect(Candidates::select(Facts::context([])))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Likely-finished games
|--------------------------------------------------------------------------
|
| Excluded when: ttb known, playtime >= ttb, and not played in the last 30 days (null counts as idle).
| All games below have a 10 hour (600 minute) time to beat.
|
*/

it('excludes a game past its time to beat that has been idle for 31 days', function () {
    $library = [Facts::game(1, minutes: 700, lastPlayed: Facts::daysAgo(31), ttbHours: 10)];

    expect(selectedIds($library))->toBe([]);
});

it('keeps a game past its time to beat that was played 3 days ago', function () {
    $library = [Facts::game(1, minutes: 700, lastPlayed: Facts::daysAgo(3), ttbHours: 10)];

    expect(selectedIds($library))->toBe([1]);
});

it('excludes a game exactly at its time to beat (at least 100%)', function () {
    $library = [Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(100), ttbHours: 10)];

    expect(selectedIds($library))->toBe([]);
});

it('excludes a game past its time to beat with no last-played date', function () {
    // The task brief: "lastPlayedAt null or older than FINISHED_IDLE_DAYS". An unknown last play is idle.
    $library = [Facts::game(1, minutes: 700, lastPlayed: null, ttbHours: 10)];

    expect(selectedIds($library))->toBe([]);
});

it('keeps a game under its time to beat however long it has been idle', function () {
    $library = [Facts::game(1, minutes: 599, lastPlayed: Facts::daysAgo(400), ttbHours: 10)];

    expect(selectedIds($library))->toBe([1]);
});

it('never excludes a game without a time to beat, even with huge playtime', function () {
    $library = [Facts::game(1, minutes: 100000, lastPlayed: Facts::daysAgo(400))];

    expect(selectedIds($library))->toBe([1]);
});

it('keeps an unplayed game that has a time to beat', function () {
    $library = [Facts::game(1, ttbHours: 10)];

    expect(selectedIds($library))->toBe([1]);
});

it('judges the collapsed entry, so a finished best entry hides the whole IGDB game', function () {
    // SPEC: the spec lists "collapse" before "exclude finished" and says the entry with the most playtime
    // supplies the user-specific inputs. Reading: collapse first, then judge that entry. Entry 1 (700 min of
    // a 10h game, idle 100 days) is the most played and finished; entry 2 (30 min, played yesterday) would
    // pass on its own but is collapsed away, so nothing is suggested for this IGDB game.
    $library = [
        Facts::game(1, minutes: 700, lastPlayed: Facts::daysAgo(100), ttbHours: 10, igdbId: 500),
        Facts::game(2, minutes: 30, lastPlayed: Facts::daysAgo(1), ttbHours: 10, igdbId: 500),
    ];

    expect(selectedIds($library))->toBe([]);
});

it('applies every rule together', function () {
    $library = [
        Facts::game(1, minutes: 50, lastPlayed: Facts::daysAgo(2), ttbHours: 10),        // kept
        Facts::game(2, unmatched: true, minutes: 50),                                     // unmatched
        Facts::game(3, minutes: 700, lastPlayed: Facts::daysAgo(90), ttbHours: 10),       // finished
        Facts::game(4, minutes: 20, igdbId: 900),                                         // collapsed away
        Facts::game(5, minutes: 40, igdbId: 900),                                         // kept
        Facts::game(6),                                                                   // kept (unplayed)
    ];

    expect(selectedIds($library))->toBe([1, 5, 6]);
});
