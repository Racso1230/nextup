<?php

use App\Steam\PlayerSummary;
use Tests\Support\SteamFixtures;

/** @return array<array-key, mixed> */
function firstPlayer(string $fixture): array
{
    $response = SteamFixtures::load($fixture)['response'];
    assert(is_array($response) && is_array($response['players']) && is_array($response['players'][0]));

    return $response['players'][0];
}

it('parses a public profile', function () {
    $summary = PlayerSummary::fromArray(firstPlayer('player_summaries_public'));

    expect($summary)->not->toBeNull()
        ->and($summary->steamId)->toBe('76561197960287930')
        ->and($summary->personaName)->toBe('Example Player')
        ->and($summary->isPublic)->toBeTrue()
        ->and($summary->profileUrl)->toBe('https://steamcommunity.com/id/exampleplayer/')
        ->and($summary->avatarFullUrl)->toEndWith('_full.jpg');
});

it('flags a private profile as not public', function () {
    expect(PlayerSummary::fromArray(firstPlayer('player_summaries_private'))?->isPublic)->toBeFalse();
});

it('rejects entries without a usable steamid or name', function (array $data) {
    expect(PlayerSummary::fromArray($data))->toBeNull();
})->with([
    'empty' => [[]],
    'numeric steamid' => [['steamid' => 76561197960287930, 'personaname' => 'x']],
    'non-digit steamid' => [['steamid' => 'abc', 'personaname' => 'x']],
    'no name' => [['steamid' => '76561197960287930']],
]);

it('treats missing urls as null', function () {
    $summary = PlayerSummary::fromArray(['steamid' => '76561197960287930', 'personaname' => 'x', 'avatarfull' => '']);

    expect($summary?->avatarFullUrl)->toBeNull()
        ->and($summary->profileUrl)->toBeNull();
});
