<?php

use App\Recommender\Engagement;
use Tests\Unit\Recommender\Support\Facts;

// With a time to beat: min(1, playtime / ttb). Here ttb = 10 hours.
it('is playtime over time to beat when the time to beat is known', function () {
    // 300 min = 5h of 10h = 0.5
    expect(Engagement::of(Facts::game(1, minutes: 300, ttbHours: 10)))->toEqualWithDelta(0.5, 1e-6)
        // 150 min = 2.5h of 10h = 0.25
        ->and(Engagement::of(Facts::game(2, minutes: 150, ttbHours: 10)))->toEqualWithDelta(0.25, 1e-6);
});

it('is exactly 1 when playtime equals the time to beat', function () {
    expect(Engagement::of(Facts::game(1, minutes: 600, ttbHours: 10)))->toEqualWithDelta(1.0, 1e-9);
});

it('saturates at 1 when playtime far exceeds the time to beat', function () {
    // 40h on a 10h game: min(1, 4) = 1
    expect(Engagement::of(Facts::game(1, minutes: 2400, ttbHours: 10)))->toEqualWithDelta(1.0, 1e-9);
});

// Without a time to beat: min(1, ln(1 + hours) / ln(1 + 50)).
it('uses a log scale without a time to beat', function () {
    // 10h: ln(11) / ln(51) = 2.397895 / 3.931826 = 0.609868
    expect(Engagement::of(Facts::game(1, minutes: 600)))->toEqualWithDelta(0.609868162, 1e-6)
        // 1h: ln(2) / ln(51) = 0.693147 / 3.931826 = 0.176291
        ->and(Engagement::of(Facts::game(2, minutes: 60)))->toEqualWithDelta(0.176291434, 1e-6);
});

it('reaches 1 at 50 hours on the log scale', function () {
    // 50h: ln(51) / ln(51) = 1
    expect(Engagement::of(Facts::game(1, minutes: 3000)))->toEqualWithDelta(1.0, 1e-9);
});

it('caps the log scale at 1', function () {
    // 500h: ln(501) / ln(51) > 1, capped
    expect(Engagement::of(Facts::game(1, minutes: 30000)))->toEqualWithDelta(1.0, 1e-9);
});

it('is 0 for an unplayed game, with or without a time to beat', function () {
    expect(Engagement::of(Facts::game(1, minutes: 0)))->toBe(0.0)
        ->and(Engagement::of(Facts::game(2, minutes: 0, ttbHours: 10)))->toBe(0.0);
});

it('grows with playtime on both scales', function () {
    expect(Engagement::of(Facts::game(1, minutes: 300)))->toBeLessThan(Engagement::of(Facts::game(2, minutes: 900)))
        ->and(Engagement::of(Facts::game(3, minutes: 100, ttbHours: 10)))->toBeLessThan(Engagement::of(Facts::game(4, minutes: 200, ttbHours: 10)));
});
