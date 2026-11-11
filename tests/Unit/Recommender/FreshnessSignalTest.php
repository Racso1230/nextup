<?php

use App\Recommender\GameFacts;
use App\Recommender\SignalName;
use App\Recommender\Signals\FreshnessSignal;
use Tests\Unit\Recommender\Support\Facts;

// Never played: 1.0. Under 1 hour and untouched for 180+ days (tried once, never returned): 0.5. Otherwise 0.

function freshness(GameFacts $game): ?float
{
    return (new FreshnessSignal)->score($game, Facts::profile());
}

it('is the freshness signal', function () {
    expect((new FreshnessSignal)->name())->toBe(SignalName::Freshness);
});

it('is 1.0 for a game that was never played', function () {
    expect(freshness(Facts::game(1)))->toEqualWithDelta(1.0, 1e-9);
});

it('is 0.5 for a game tried for under an hour and untouched for 181 days', function () {
    expect(freshness(Facts::game(1, minutes: 45, lastPlayed: Facts::daysAgo(181))))->toEqualWithDelta(0.5, 1e-9);
});

it('is 0.5 at the boundaries: 59 minutes and exactly 180 days idle', function () {
    // The spec says "180+ days", so exactly 180 counts.
    expect(freshness(Facts::game(1, minutes: 59, lastPlayed: Facts::daysAgo(180))))->toEqualWithDelta(0.5, 1e-9);
});

it('is 0 for a short game that was played 10 days ago', function () {
    expect(freshness(Facts::game(1, minutes: 45, lastPlayed: Facts::daysAgo(10))))->toEqualWithDelta(0.0, 1e-9);
});

it('is 0 for a short game idle for 179 days', function () {
    expect(freshness(Facts::game(1, minutes: 45, lastPlayed: Facts::daysAgo(179))))->toEqualWithDelta(0.0, 1e-9);
});

it('is 0 once a game has an hour or more of playtime, however long it has been idle', function () {
    // "Under 1 hour": exactly 60 minutes is not under.
    expect(freshness(Facts::game(1, minutes: 60, lastPlayed: Facts::daysAgo(400))))->toEqualWithDelta(0.0, 1e-9)
        ->and(freshness(Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(400))))->toEqualWithDelta(0.0, 1e-9);
});

it('treats a short played game with no last-played date as untouched', function () {
    // SPEC: "untouched for 180+ days" is silent on lastPlayedAt = null for a played game. Reading: an unknown
    // last play counts as idle (the same reading as the finished and dropped rules), so 0.5.
    expect(freshness(Facts::game(1, minutes: 45, lastPlayed: null)))->toEqualWithDelta(0.5, 1e-9);
});
