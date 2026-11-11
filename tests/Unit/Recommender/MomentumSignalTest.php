<?php

use App\Recommender\GameFacts;
use App\Recommender\SignalName;
use App\Recommender\Signals\MomentumSignal;
use Tests\Unit\Recommender\Support\Facts;

// Momentum = max( min(1, recent_hours / 5), exp(-days_since_last_played / 14) ). Unplayed = 0.
// "Recent hours" come from snapshot minutes when not null, otherwise from Steam's playtime_2weeks.

function momentum(GameFacts $game): ?float
{
    return (new MomentumSignal)->score($game, Facts::profile());
}

it('is the momentum signal', function () {
    expect((new MomentumSignal)->name())->toBe(SignalName::Momentum);
});

it('decays with days since last played: yesterday is about 0.93', function () {
    // exp(-1/14) = 0.931063
    expect(momentum(Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(1))))->toEqualWithDelta(0.931062780, 1e-6);
});

it('decays with days since last played: a month ago is about 0.117', function () {
    // exp(-30/14) = 0.117319
    expect(momentum(Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(30))))->toEqualWithDelta(0.117319166, 1e-6);
});

it('is about 0 for a game last played a year ago', function () {
    // exp(-365/14) = 4.7e-12
    expect(momentum(Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(365))))->toEqualWithDelta(0.0, 1e-6);
});

it('uses snapshot minutes for recent hours when they are not null', function () {
    // lastPlayed 400 days ago makes the recency part ~0, so the recent-hours part is the whole answer.
    // 300 min = 5h: min(1, 5/5) = 1.0     150 min = 2.5h: 2.5/5 = 0.5
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(400), snapshotRecent: 300)))->toEqualWithDelta(1.0, 1e-6)
        ->and(momentum(Facts::game(2, minutes: 900, lastPlayed: Facts::daysAgo(400), snapshotRecent: 150)))->toEqualWithDelta(0.5, 1e-6);
});

it('falls back to playtime_2weeks when snapshots do not cover the window', function () {
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(400), twoWeeks: 300)))->toEqualWithDelta(1.0, 1e-6)
        ->and(momentum(Facts::game(2, minutes: 900, lastPlayed: Facts::daysAgo(400), twoWeeks: 150)))->toEqualWithDelta(0.5, 1e-6);
});

it('prefers snapshot minutes over playtime_2weeks when both exist', function () {
    // snapshot 60 min = 1h -> 0.2; playtime_2weeks 300 min would give 1.0 but must be ignored.
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(400), twoWeeks: 300, snapshotRecent: 60)))->toEqualWithDelta(0.2, 1e-6);
});

it('trusts a snapshot value of 0 rather than falling back', function () {
    // The snapshots cover the window and show no play, so playtime_2weeks (300) is not consulted.
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(400), twoWeeks: 300, snapshotRecent: 0)))->toEqualWithDelta(0.0, 1e-6);
});

it('caps recent hours at 1', function () {
    // 600 min = 10h: min(1, 10/5) = 1
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(1), snapshotRecent: 600)))->toEqualWithDelta(1.0, 1e-9);
});

it('takes the larger of recent hours and recency', function () {
    // Recent hours 150 min -> 0.5; played yesterday -> 0.931063. Recency wins.
    expect(momentum(Facts::game(1, minutes: 900, lastPlayed: Facts::daysAgo(1), snapshotRecent: 150)))->toEqualWithDelta(0.931062780, 1e-6)
        // Recent hours 300 min -> 1.0; played yesterday -> 0.931063. Recent hours win.
        ->and(momentum(Facts::game(2, minutes: 900, lastPlayed: Facts::daysAgo(1), snapshotRecent: 300)))->toEqualWithDelta(1.0, 1e-6)
        // Recent hours 150 min -> 0.5; played 5 days ago -> exp(-5/14) = 0.699673. Recency wins.
        ->and(momentum(Facts::game(3, minutes: 900, lastPlayed: Facts::daysAgo(5), snapshotRecent: 150)))->toEqualWithDelta(0.699672537, 1e-6);
});

it('is 0 for an unplayed game', function () {
    expect(momentum(Facts::game(1, minutes: 0)))->toEqualWithDelta(0.0, 1e-9);
});

it('is 0 when there is no last-played date and no recent minutes', function () {
    expect(momentum(Facts::game(1, minutes: 600, lastPlayed: null)))->toEqualWithDelta(0.0, 1e-9);
});

it('still uses recent minutes when there is no last-played date', function () {
    expect(momentum(Facts::game(1, minutes: 600, lastPlayed: null, twoWeeks: 150)))->toEqualWithDelta(0.5, 1e-6);
});

it('is always within 0..1', function () {
    foreach ([0, 1, 7, 30, 400] as $days) {
        foreach ([null, 0, 90, 600] as $recent) {
            $value = momentum(Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo($days), snapshotRecent: $recent));

            expect($value)->toBeGreaterThanOrEqual(0.0)->toBeLessThanOrEqual(1.0);
        }
    }
});
