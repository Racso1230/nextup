<?php

use App\Steam\SteamCdn;

test('it builds the community icon url from the appid and hash', function () {
    expect(SteamCdn::iconUrl(1145360, 'abc123'))
        ->toBe('https://media.steampowered.com/steamcommunity/public/images/apps/1145360/abc123.jpg');
});

test('it returns null without a hash', function (?string $hash) {
    expect(SteamCdn::iconUrl(1145360, $hash))->toBeNull();
})->with([null, '']);
