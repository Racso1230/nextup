<?php

use App\Recommender\GameFacts;
use App\Recommender\SignalName;
use App\Recommender\Signals\FitSignal;
use Tests\Unit\Recommender\Support\Facts;

// Fit = 1 / (1 + remaining_hours / 10), remaining = max(0, ttb - playtime). Unknown ttb = 0.5, never 0.

function fit(GameFacts $game): ?float
{
    return (new FitSignal)->score($game, Facts::profile());
}

it('is the fit signal', function () {
    expect((new FitSignal)->name())->toBe(SignalName::Fit);
});

it('is 1.0 with no time remaining', function () {
    // ttb 10h, played 10h: remaining 0 -> 1 / (1 + 0) = 1
    expect(fit(Facts::game(1, minutes: 600, ttbHours: 10)))->toEqualWithDelta(1.0, 1e-9);
});

it('is 0.5 with 10 hours remaining', function () {
    // ttb 20h, played 10h: remaining 10 -> 1 / (1 + 1) = 0.5
    expect(fit(Facts::game(1, minutes: 600, ttbHours: 20)))->toEqualWithDelta(0.5, 1e-9);
});

it('is 0.2 with 40 hours remaining', function () {
    // ttb 50h, played 10h: remaining 40 -> 1 / (1 + 4) = 0.2
    expect(fit(Facts::game(1, minutes: 600, ttbHours: 50)))->toEqualWithDelta(0.2, 1e-9);
});

it('is 2/3 with 5 hours remaining', function () {
    // ttb 5h, unplayed: remaining 5 -> 1 / (1 + 0.5) = 0.666667; ttb 10h, unplayed: 1 / (1 + 1) = 0.5
    expect(fit(Facts::game(1, ttbHours: 5)))->toEqualWithDelta(0.666666667, 1e-6)
        ->and(fit(Facts::game(2, ttbHours: 10)))->toEqualWithDelta(0.5, 1e-9);
});

it('clamps to 1.0 when played past the time to beat', function () {
    // ttb 10h, played 15h: remaining max(0, -5) = 0 -> 1.0, not above 1
    expect(fit(Facts::game(1, minutes: 900, ttbHours: 10)))->toEqualWithDelta(1.0, 1e-9);
});

it('is exactly 0.5 when the time to beat is unknown, never 0', function () {
    expect(fit(Facts::game(1)))->toBe(0.5)
        ->and(fit(Facts::game(2, minutes: 100000)))->toBe(0.5);
});
