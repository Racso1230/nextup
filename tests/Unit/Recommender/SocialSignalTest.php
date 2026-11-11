<?php

use App\Recommender\SignalName;
use App\Recommender\Signals\SocialSignal;
use Tests\Unit\Recommender\Support\Facts;

// Friends arrive in Phase 4, so in v1 social is null for everyone.

it('is the social signal', function () {
    expect((new SocialSignal)->name())->toBe(SignalName::Social);
});

it('is always null in v1', function () {
    $signal = new SocialSignal;
    $profile = Facts::profile(playedCount: 10);

    expect($signal->score(Facts::game(1), $profile))->toBeNull()
        ->and($signal->score(Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(1), ttbHours: 10, tags: [Facts::genre(1)]), $profile))->toBeNull();
});
