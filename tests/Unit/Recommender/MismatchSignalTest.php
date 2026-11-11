<?php

use App\Recommender\ProfileBuilder;
use App\Recommender\SignalName;
use App\Recommender\Signals\MismatchSignal;
use Tests\Unit\Recommender\Support\Facts;

// mismatch = cosine(dropped vector, candidate tag vector), with the candidate's own contribution removed if
// it is itself dropped. No dropped games means 0.
//
// SPEC: as for taste, the candidate vector is read as type weight x idf (see TasteSignalTest). The exact-value
// test depends on that reading; the ordering tests don't.

it('is the mismatch signal', function () {
    expect((new MismatchSignal)->name())->toBe(SignalName::Mismatch);
});

it('is 0 when the user has dropped nothing', function () {
    $profile = Facts::profile(idf: ['genre:1' => 1.0], playedCount: 6);

    expect((new MismatchSignal)->score(Facts::game(1, tags: [Facts::genre(1)]), $profile))->toEqualWithDelta(0.0, 1e-9);
});

it('is the cosine of the dropped vector and the candidate tag vector', function () {
    // dropped vector (genre:1 = 3, genre:2 = 4), idf genre:1 = 1, genre:2 = 2, so the candidate is (1, 2):
    // (3 + 8) / (5 x sqrt(5)) = 0.983870
    $profile = Facts::profile(
        idf: ['genre:1' => 1.0, 'genre:2' => 2.0],
        dropped: ['genre:1' => 3.0, 'genre:2' => 4.0],
    );
    $candidate = Facts::game(99, tags: [Facts::genre(1), Facts::genre(2)]);

    expect((new MismatchSignal)->score($candidate, $profile))->toEqualWithDelta(0.983869910, 1e-6);
});

it('scores a candidate similar to dropped games above a dissimilar one', function () {
    // Game 1 was dropped (30 min, idle 100 days) and is tagged genre:1 + genre:2. Candidate 2 shares both tags,
    // candidate 3 shares none.
    $library = [
        Facts::game(1, minutes: 30, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(2, tags: [Facts::genre(1), Facts::genre(2)]),
        Facts::game(3, tags: [Facts::genre(8), Facts::genre(9)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));
    $signal = new MismatchSignal;

    expect($signal->score($library[1], $profile))->toBeGreaterThan($signal->score($library[2], $profile))
        ->and($signal->score($library[1], $profile))->toBeGreaterThan(0.0)
        ->and($signal->score($library[2], $profile))->toEqualWithDelta(0.0, 1e-9);
});

it('does not let a dropped candidate match itself', function () {
    // Dropped games 1 (tags genre:1, genre:2) and 2 (tag genre:3). Scoring game 1 removes its own
    // contribution, leaving only game 2's genre:3, which game 1 does not share: 0. Without leave-one-out
    // it would be 1.0. Game 3 is not dropped and has game 1's tags, so it does match.
    $profile = Facts::profile(
        idf: ['genre:1' => 1.0, 'genre:2' => 2.0, 'genre:3' => 1.5],
        dropped: ['genre:1' => 1.0, 'genre:2' => 2.0, 'genre:3' => 1.5],
        droppedContributions: [
            1 => ['genre:1' => 1.0, 'genre:2' => 2.0],
            2 => ['genre:3' => 1.5],
        ],
    );
    $signal = new MismatchSignal;

    $droppedOne = Facts::game(1, minutes: 30, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(1), Facts::genre(2)]);
    $droppedTwo = Facts::game(2, minutes: 30, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(3)]);
    $lookalike = Facts::game(3, tags: [Facts::genre(1), Facts::genre(2)]);

    expect($signal->score($droppedOne, $profile))->toEqualWithDelta(0.0, 1e-9)
        ->and($signal->score($droppedTwo, $profile))->toEqualWithDelta(0.0, 1e-9)
        ->and($signal->score($lookalike, $profile))->toBeGreaterThan(0.0);
});

it('is 0 for the only dropped game scored against itself', function () {
    $library = [
        Facts::game(1, minutes: 30, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(1)]),
        Facts::game(2, tags: [Facts::genre(2)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    // SPEC: "no dropped games means 0" is read to also cover "no OTHER dropped games", so 0.0 (not null).
    expect((new MismatchSignal)->score($library[0], $profile))->toEqualWithDelta(0.0, 1e-9);
});

it('is always within 0..1', function () {
    $library = [
        Facts::game(1, minutes: 30, lastPlayed: Facts::daysAgo(100), tags: [Facts::genre(1), Facts::theme(2), Facts::keyword(3)]),
        Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(2), ttbHours: 10, tags: [Facts::genre(1), Facts::mode(1)]),
        Facts::game(3, tags: [Facts::genre(1), Facts::genre(2), Facts::theme(2)]),
        Facts::game(4, tags: []),
        Facts::game(5, tags: [Facts::perspective(9)]),
    ];
    $profile = ProfileBuilder::build(Facts::context($library));

    foreach ($library as $game) {
        expect((new MismatchSignal)->score($game, $profile))->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
    }
});
