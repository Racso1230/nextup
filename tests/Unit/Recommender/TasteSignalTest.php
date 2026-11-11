<?php

use App\Recommender\GameFacts;
use App\Recommender\Profile;
use App\Recommender\ProfileBuilder;
use App\Recommender\SignalName;
use App\Recommender\Signals\TasteSignal;
use Tests\Unit\Recommender\Support\Facts;

/**
 * A hand-built profile with taste vector (genre:1 = 3, genre:2 = 4) and idf genre:1 = 1, genre:2 = 2.
 * A candidate tagged genre:1 + genre:2 has the vector (1, 2).
 *
 * SPEC: the spec says "cosine between the taste vector and the candidate's tag vector" and defines tag
 * weights by type and IDF, but does not say outright whether the candidate vector is weighted the same way
 * (type weight x idf) or is a plain 0/1 indicator. Reading: the same weights as the taste vector, so both
 * sides live in the same space. The exact-value tests below depend on that reading; the ordering tests don't.
 *
 * cosine((3,4), (1,2)) = (3 + 8) / (5 x sqrt(5)) = 11 / 11.18034 = 0.983870
 */
function tasteProfile(int $playedCount): Profile
{
    return Facts::profile(
        idf: ['genre:1' => 1.0, 'genre:2' => 2.0],
        taste: ['genre:1' => 3.0, 'genre:2' => 4.0],
        playedCount: $playedCount,
    );
}

function tasteCandidate(): GameFacts
{
    return Facts::game(99, tags: [Facts::genre(1), Facts::genre(2)]);
}

it('is the taste signal', function () {
    expect((new TasteSignal)->name())->toBe(SignalName::Taste);
});

it('is the cosine of the taste vector and the candidate tag vector with 5 or more played games', function () {
    expect((new TasteSignal)->score(tasteCandidate(), tasteProfile(5)))->toEqualWithDelta(0.983869910, 1e-6);
});

it('does not scale up past 5 played games', function () {
    expect((new TasteSignal)->score(tasteCandidate(), tasteProfile(40)))->toEqualWithDelta(0.983869910, 1e-6);
});

it('scales taste by played_count / 5 for a tiny library', function () {
    // 3 played games: 0.983870 x 3/5 = 0.590322
    expect((new TasteSignal)->score(tasteCandidate(), tasteProfile(3)))->toEqualWithDelta(0.590321946, 1e-6);
});

it('is null when there are no played games', function () {
    $profile = Facts::profile(idf: ['genre:1' => 1.0], playedCount: 0);

    expect((new TasteSignal)->score(Facts::game(1, tags: [Facts::genre(1)]), $profile))->toBeNull();
});

it('is 0 for a candidate sharing no tags with the taste vector', function () {
    $profile = tasteProfile(5);
    $candidate = Facts::game(99, tags: [Facts::genre(7)]);

    expect((new TasteSignal)->score($candidate, $profile))->toEqualWithDelta(0.0, 1e-9);
});

it('scores a candidate with the distinctive tags of a heavily played game above one with only common tags', function () {
    // Game 1 is the heavily played one (engagement 1) with genre:1 (on nearly everything) and genre:5 (rare).
    // Candidate 7 shares only the common genre:1 (plus its own genre:7); candidate 8 shares only the rare
    // genre:5 (plus its own genre:8). idf: genre:1 = ln(8/7)+1 = 1.133531, genre:5 = ln(8/3)+1 = 1.980829.
    $library = [
        Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(1), Facts::genre(5)]),
        Facts::game(2, tags: [Facts::genre(1)]),
        Facts::game(3, tags: [Facts::genre(1)]),
        Facts::game(4, tags: [Facts::genre(1)]),
        Facts::game(5, tags: [Facts::genre(1)]),
        Facts::game(7, tags: [Facts::genre(1), Facts::genre(7)]),
        Facts::game(8, tags: [Facts::genre(5), Facts::genre(8)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    $common = (new TasteSignal)->score($library[5], $profile);       // game 7
    $distinctive = (new TasteSignal)->score($library[6], $profile);  // game 8

    expect($distinctive)->toBeGreaterThan($common)
        ->and($common)->toBeGreaterThan(0.0);
});

/*
|--------------------------------------------------------------------------
| Leave-one-out
|--------------------------------------------------------------------------
*/

it('removes a played game own contribution, so it does not match itself', function () {
    // Game 1 and game 2 are played, with disjoint tags. Scoring game 1 must use a taste vector of only
    // game 2's tags, which share nothing with game 1: cosine 0. Without leave-one-out it would be 1.
    $library = [
        Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(3)]),
        Facts::game(3, tags: [Facts::genre(1), Facts::genre(2)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));
    $signal = new TasteSignal;

    expect($signal->score($library[0], $profile))->toEqualWithDelta(0.0, 1e-9)
        ->and($signal->score($library[1], $profile))->toEqualWithDelta(0.0, 1e-9)
        // An unplayed lookalike of game 1 has no contribution to remove and does match the (genre:1, 2) taste.
        ->and($signal->score($library[2], $profile))->toBeGreaterThan(0.0);
});

it('is null for the only played game, because nothing else is left to learn taste from', function () {
    // SPEC: with leave-one-out the only played game leaves an empty taste vector. The spec says "no played
    // games gives null" and "its own contribution is removed first", but does not say whether the played
    // count is re-counted after the removal. Reading: it is (0 other played games), so the result is null,
    // not 0.0. Either way the game must not score high against itself; this test pins down null.
    $library = [
        Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(2, tags: [Facts::genre(3)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    expect((new TasteSignal)->score($library[0], $profile))->toBeNull();
});

it('is null for every candidate when nothing has been played', function () {
    $library = [
        Facts::game(1, tags: [Facts::genre(1)]),
        Facts::game(2, tags: [Facts::genre(2)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    expect((new TasteSignal)->score($library[0], $profile))->toBeNull()
        ->and((new TasteSignal)->score($library[1], $profile))->toBeNull();
});

it('always returns a value in 0..1 or null', function () {
    $library = [
        Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(1), Facts::theme(2), Facts::keyword(3)]),
        Facts::game(2, minutes: 300, lastPlayed: Facts::daysAgo(9), tags: [Facts::genre(1), Facts::mode(1)]),
        Facts::game(3, minutes: 50, lastPlayed: Facts::daysAgo(200), tags: [Facts::genre(2)]),
        Facts::game(4, tags: [Facts::genre(1), Facts::genre(2), Facts::theme(2)]),
        Facts::game(5, tags: []),
        Facts::game(6, tags: [Facts::perspective(9)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    foreach ($library as $game) {
        $score = (new TasteSignal)->score($game, $profile);

        if ($score !== null) {
            expect($score)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});
