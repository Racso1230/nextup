<?php

use App\Steam\SteamPrivacy;

it('points at the steam privacy settings page over https', function () {
    expect(parse_url(SteamPrivacy::SETTINGS_URL, PHP_URL_SCHEME))->toBe('https')
        ->and(parse_url(SteamPrivacy::SETTINGS_URL, PHP_URL_HOST))->toBe('steamcommunity.com');
});

it('lists the steps in order', function () {
    expect(SteamPrivacy::steps())->not->toBeEmpty()
        ->and(SteamPrivacy::steps()[1])->toContain('Game details');
});
