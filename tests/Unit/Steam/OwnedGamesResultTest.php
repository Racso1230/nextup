<?php

use App\Steam\OwnedGame;
use App\Steam\OwnedGamesResult;
use Tests\Support\SteamFixtures;

it('parses a public library, skipping missing appids and deduping', function () {
    $result = OwnedGamesResult::fromArray(SteamFixtures::load('owned_games_public'));

    expect($result->isPrivate)->toBeFalse()
        ->and(array_map(fn (OwnedGame $g) => $g->appId, $result->games))
        ->toBe([440, 570, 730, 620, 400, 220, 292030]);

    $dota = $result->games[1];
    expect($dota->name)->toBe('Dota 2')
        ->and($dota->playtimeMinutes)->toBe(90);
});

it('keeps playtime in minutes and reads the two week figure', function () {
    $games = OwnedGamesResult::fromArray(SteamFixtures::load('owned_games_public'))->games;

    expect($games[0]->playtimeMinutes)->toBe(4500)
        ->and($games[0]->playtime2WeeksMinutes)->toBe(120)
        ->and($games[0]->iconHash)->toBe('e3f595a92552da3d664ad00277fad2107345f743')
        ->and($games[0]->lastPlayedAt?->getTimestamp())->toBe(1730000000)
        ->and($games[1]->playtime2WeeksMinutes)->toBeNull();
});

it('maps rtime_last_played of 0 to null', function () {
    $never = OwnedGamesResult::fromArray(SteamFixtures::load('owned_games_public'))->games[4];

    expect($never->appId)->toBe(400)
        ->and($never->lastPlayedAt)->toBeNull();
});

it('treats an empty response as a private library', function () {
    $result = OwnedGamesResult::fromArray(SteamFixtures::load('owned_games_private'));

    expect($result->isPrivate)->toBeTrue()
        ->and($result->games)->toBe([]);
});

it('treats game_count 0 as a public, empty library', function () {
    $result = OwnedGamesResult::fromArray(SteamFixtures::load('owned_games_empty'));

    expect($result->isPrivate)->toBeFalse()
        ->and($result->games)->toBe([]);

    expect(OwnedGamesResult::fromArray(['response' => ['game_count' => 0]])->isPrivate)->toBeFalse();
});

it('never reads malformed bodies as zero games', function (array $body) {
    expect(OwnedGamesResult::fromArray($body)->isPrivate)->toBeTrue();
})->with([
    'no response key' => [[]],
    'response is a string' => [['response' => 'nope']],
    'unrelated keys only' => [['response' => ['foo' => 'bar']]],
]);

it('skips malformed entries instead of throwing', function () {
    $result = OwnedGamesResult::fromArray(['response' => ['games' => [
        'junk',
        ['appid' => '440'],
        ['appid' => -1],
        ['appid' => 10, 'name' => 5, 'playtime_forever' => 'lots', 'rtime_last_played' => 'x'],
    ]]]);

    expect($result->games)->toHaveCount(1)
        ->and($result->games[0]->appId)->toBe(10)
        ->and($result->games[0]->name)->toBe('App 10')
        ->and($result->games[0]->playtimeMinutes)->toBe(0)
        ->and($result->games[0]->lastPlayedAt)->toBeNull();
});

it('has named constructors', function () {
    expect(OwnedGamesResult::private()->isPrivate)->toBeTrue()
        ->and(OwnedGamesResult::withGames([])->isPrivate)->toBeFalse();
});
