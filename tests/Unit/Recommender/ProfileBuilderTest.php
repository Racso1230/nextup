<?php

use App\Recommender\GameFacts;
use App\Recommender\ProfileBuilder;
use Tests\Unit\Recommender\Support\Facts;

/**
 * Small library used by most tests below. Tag keys: genre:1 (Adventure-like, on every game), genre:2,
 * keyword:1, game_mode:1.
 *
 *   1  played 600 min, ttb 10h  -> engagement 1.0   tags genre:1, genre:2
 *   2  played 300 min, ttb 10h  -> engagement 0.5   tags genre:1, keyword:1
 *   3  unplayed                                      tags genre:1, game_mode:1
 *   4  played 100 min, tagless                       (not in N, not in playedCount)
 *
 * N = 3 tagged games. df: genre:1 = 3, genre:2 = 1, keyword:1 = 1, game_mode:1 = 1.
 *
 * @return list<GameFacts>
 */
function profileLibrary(): array
{
    return [
        Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(5), ttbHours: 10, tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(2, minutes: 300, lastPlayed: Facts::daysAgo(5), ttbHours: 10, tags: [Facts::genre(1), Facts::keyword(1)]),
        Facts::game(3, tags: [Facts::genre(1), Facts::mode(1)]),
        Facts::game(4, minutes: 100, lastPlayed: Facts::daysAgo(5)),
    ];
}

it('computes IDF over every game with at least one tag, played or not', function () {
    $profile = ProfileBuilder::build(Facts::context(profileLibrary()));

    // idf = ln((1 + N) / (1 + df)) + 1, with N = 3
    // genre:1       df 3: ln(4/4) + 1 = 1.0
    // genre:2       df 1: ln(4/2) + 1 = 1.693147
    // keyword:1     df 1: 1.693147
    // game_mode:1   df 1: 1.693147 (game 3 is unplayed but still counts)
    Facts::assertVector($profile->idf, [
        'genre:1' => 1.0,
        'genre:2' => 1.693147181,
        'keyword:1' => 1.693147181,
        'game_mode:1' => 1.693147181,
    ]);
});

it('gives common tags a lower IDF than distinctive ones', function () {
    $profile = ProfileBuilder::build(Facts::context(profileLibrary()));

    expect($profile->idf['genre:1'])->toBeLessThan($profile->idf['genre:2']);
});

it('does not count tagless games in N', function () {
    // Same library without game 4 must give the same IDF, because game 4 has no tags.
    $with = ProfileBuilder::build(Facts::context(profileLibrary()));
    $without = ProfileBuilder::build(Facts::context(array_slice(profileLibrary(), 0, 3)));

    Facts::assertVector($with->idf, $without->idf);
});

it('builds the taste vector from engagement x type weight x idf, summed over played games', function () {
    $profile = ProfileBuilder::build(Facts::context(profileLibrary()));

    // genre:1   game 1: 1.0 x 1.0 x 1.0           = 1.0      + game 2: 0.5 x 1.0 x 1.0 = 0.5     -> 1.5
    // genre:2   game 1: 1.0 x 1.0 x 1.693147      = 1.693147
    // keyword:1 game 2: 0.5 x 0.25 x 1.693147     = 0.211643
    // game_mode:1 only on the unplayed game 3, so absent.
    Facts::assertVector($profile->tasteVector, [
        'genre:1' => 1.5,
        'genre:2' => 1.693147181,
        'keyword:1' => 0.211643398,
    ]);
});

it('records each played game contribution, and they add up to the taste vector', function () {
    $profile = ProfileBuilder::build(Facts::context(profileLibrary()));

    Facts::assertVector($profile->tasteContributions[1], ['genre:1' => 1.0, 'genre:2' => 1.693147181]);
    Facts::assertVector($profile->tasteContributions[2], ['genre:1' => 0.5, 'keyword:1' => 0.211643398]);

    $summed = [];
    foreach ($profile->tasteContributions as $contribution) {
        foreach ($contribution as $key => $value) {
            $summed[$key] = ($summed[$key] ?? 0.0) + $value;
        }
    }

    Facts::assertVector($summed, $profile->tasteVector);
});

it('lets unplayed games add nothing to taste', function () {
    $profile = ProfileBuilder::build(Facts::context(profileLibrary()));

    expect($profile->tasteContributions)->not->toHaveKey(3)
        ->and($profile->tasteVector)->not->toHaveKey('game_mode:1');
});

it('weights tag types: genre and theme 1, mode and perspective 0.5, keyword 0.25', function () {
    // One played game with engagement 1 (60 min of a 1h ttb). N = 1 and df = 1 for every tag, so
    // idf = ln(2/2) + 1 = 1 and the vector is just the type weights.
    $game = Facts::game(1, minutes: 60, lastPlayed: Facts::daysAgo(1), ttbHours: 1, tags: [
        Facts::genre(1), Facts::theme(1), Facts::mode(1), Facts::perspective(1), Facts::keyword(1),
    ]);

    $profile = ProfileBuilder::build(Facts::context([$game]));

    Facts::assertVector($profile->tasteVector, [
        'genre:1' => 1.0,
        'theme:1' => 1.0,
        'game_mode:1' => 0.5,
        'perspective:1' => 0.5,
        'keyword:1' => 0.25,
    ]);
});

it('counts played games that have at least one tag', function () {
    // Games 1 and 2 are played and tagged; 3 is unplayed; 4 is played but tagless.
    expect(ProfileBuilder::build(Facts::context(profileLibrary()))->playedCount)->toBe(2);
});

it('carries the run time through', function () {
    expect(ProfileBuilder::build(Facts::context(profileLibrary()))->now)->toEqual(Facts::now());
});

it('has an empty taste and dropped vector for an empty library', function () {
    $profile = ProfileBuilder::build(Facts::context([]));

    expect($profile->idf)->toBe([])
        ->and($profile->tasteVector)->toBe([])
        ->and($profile->droppedVector)->toBe([])
        ->and($profile->playedCount)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Dropped games
|--------------------------------------------------------------------------
|
| Dropped = played, (under 2 hours OR under 15% of the time to beat), AND not played for 60+ days.
|
*/

it('finds dropped games and weights them by type x idf, unscaled by engagement', function () {
    //   1  60 min, no ttb, 100 days idle, tags genre:1 keyword:1   -> dropped (under 2h)
    //   2  600 min, ttb 100h, 90 days idle, tags genre:2           -> dropped (10% of ttb, under 15%)
    //   3  60 min, 10 days idle, tags genre:1                      -> NOT dropped (played recently)
    //   4  600 min, ttb 10h, 200 days idle, tags genre:1           -> NOT dropped (100% of ttb, over 2h)
    //   5  130 min, no ttb, 200 days idle, tags genre:1            -> NOT dropped (over 2h, no ttb)
    //   6  unplayed, tags genre:1                                  -> NOT dropped (never played)
    $library = [
        Facts::game(1, minutes: 60, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(1), Facts::keyword(1)]),
        Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(90), ttbHours: 100, tags: [Facts::genre(2)]),
        Facts::game(3, minutes: 60, lastPlayed: Facts::daysAgo(10), tags: [Facts::genre(1)]),
        Facts::game(4, minutes: 600, lastPlayed: Facts::daysAgo(200), ttbHours: 10, tags: [Facts::genre(1)]),
        Facts::game(5, minutes: 130, lastPlayed: Facts::daysAgo(200), tags: [Facts::genre(1)]),
        Facts::game(6, tags: [Facts::genre(1)]),
    ];

    $profile = ProfileBuilder::build(Facts::context($library));

    // N = 6. df: genre:1 = 5 (games 1,3,4,5,6), genre:2 = 1, keyword:1 = 1.
    // idf genre:1 = ln(7/6) + 1 = 1.154151; genre:2 = keyword:1 = ln(7/2) + 1 = 2.252763
    // Game 1: genre:1 = 1.0 x 1.154151 = 1.154151; keyword:1 = 0.25 x 2.252763 = 0.563191
    // Game 2: genre:2 = 1.0 x 2.252763 (engagement is 0.1 but is NOT applied to dropped games)
    expect(array_keys($profile->droppedContributions))->toEqualCanonicalizing([1, 2]);

    Facts::assertVector($profile->droppedContributions[1], ['genre:1' => 1.154150680, 'keyword:1' => 0.563190742]);
    Facts::assertVector($profile->droppedContributions[2], ['genre:2' => 2.252762968]);
    Facts::assertVector($profile->droppedVector, [
        'genre:1' => 1.154150680,
        'keyword:1' => 0.563190742,
        'genre:2' => 2.252762968,
    ]);

    // Games 1-5 are played and tagged.
    expect($profile->playedCount)->toBe(5);
});

it('uses 120 minutes and 60 idle days as the exact boundaries', function () {
    //   1  119 min, 60 days idle  -> dropped (under 2h, idle 60+)
    //   2  120 min, 60 days idle  -> NOT dropped (not under 2h; no ttb)
    //   3  119 min, 59 days idle  -> NOT dropped (played within the last 60 days)
    $library = [
        Facts::game(1, minutes: 119, lastPlayed: Facts::daysAgo(60), tags: [Facts::genre(1)]),
        Facts::game(2, minutes: 120, lastPlayed: Facts::daysAgo(60), tags: [Facts::genre(1)]),
        Facts::game(3, minutes: 119, lastPlayed: Facts::daysAgo(59), tags: [Facts::genre(1)]),
    ];

    $profile = ProfileBuilder::build(Facts::context($library));

    expect(array_keys($profile->droppedContributions))->toBe([1]);
});

it('does not treat a recently played short game as dropped', function () {
    $library = [Facts::game(1, minutes: 30, lastPlayed: Facts::daysAgo(3), tags: [Facts::genre(1)])];

    $profile = ProfileBuilder::build(Facts::context($library));

    expect($profile->droppedContributions)->toBe([])
        ->and($profile->droppedVector)->toBe([]);
});

it('treats a short played game with no last-played date as idle, hence dropped', function () {
    // SPEC: "not played for 60+ days" does not say what a played game with lastPlayedAt = null means.
    // Reading: unknown last play is treated as idle, matching the finished rule in the task brief
    // ("lastPlayedAt null or older than FINISHED_IDLE_DAYS"). Steam normally omits it only for old data.
    $library = [Facts::game(1, minutes: 30, lastPlayed: null, tags: [Facts::genre(1)])];

    $profile = ProfileBuilder::build(Facts::context($library));

    expect(array_keys($profile->droppedContributions))->toBe([1]);
});

it('never drops an unplayed game', function () {
    $library = [Facts::game(1, minutes: 0, lastPlayed: null, tags: [Facts::genre(1)])];

    expect(ProfileBuilder::build(Facts::context($library))->droppedContributions)->toBe([]);
});
