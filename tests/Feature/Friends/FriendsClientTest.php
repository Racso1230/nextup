<?php

use App\Steam\SteamApiException;
use App\Steam\SteamClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\SteamFixtures;

const FRIENDS_TEST_KEY = 'TESTKEY0123456789ABCDEF';
const FRIENDS_TEST_ID = '76561197960287930';

function friendsClient(): SteamClient
{
    return new SteamClient(FRIENDS_TEST_KEY, app(Factory::class));
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('reads a public friends list as steam ids', function () {
    Http::fake(['*GetFriendList*' => Http::response(SteamFixtures::load('friends_public'))]);

    expect(friendsClient()->friendIds(FRIENDS_TEST_ID))
        ->toBe(['76561198000000000', '76561198000000001', '76561198000000002']);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.steampowered.com/ISteamUser/GetFriendList/v1/?')
            && $query['steamid'] === FRIENDS_TEST_ID
            && $query['relationship'] === 'friend'
            && $query['key'] === FRIENDS_TEST_KEY;
    });
});

it('reads an empty public friends list as an empty array', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('friends_empty'))]);

    expect(friendsClient()->friendIds(FRIENDS_TEST_ID))->toBe([]);
});

it('treats 401 and 403 from the friends list as private', function (int $status) {
    Http::fake(['*' => Http::response('', $status)]);

    expect(friendsClient()->friendIds(FRIENDS_TEST_ID))->toBeNull();
})->with([401, 403]);

it('still throws on a server error from the friends list', function () {
    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => friendsClient()->friendIds(FRIENDS_TEST_ID))->toThrow(SteamApiException::class, 'HTTP 500');
});

it('reads recently played appids', function () {
    Http::fake(['*GetRecentlyPlayedGames*' => Http::response(SteamFixtures::load('recently_played_public'))]);

    expect(friendsClient()->recentlyPlayedAppIds(FRIENDS_TEST_ID))->toBe([440, 570, 620]);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.steampowered.com/IPlayerService/GetRecentlyPlayedGames/v1/?')
            && $query['steamid'] === FRIENDS_TEST_ID;
    });
});

it('returns an empty array for a public profile with no recent play', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('recently_played_empty'))]);

    expect(friendsClient()->recentlyPlayedAppIds(FRIENDS_TEST_ID))->toBe([]);
});

it('returns null for a private profile', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('recently_played_private'))]);

    expect(friendsClient()->recentlyPlayedAppIds(FRIENDS_TEST_ID))->toBeNull();
});

it('treats 401 from recently played as private', function () {
    Http::fake(['*' => Http::response('', 401)]);

    expect(friendsClient()->recentlyPlayedAppIds(FRIENDS_TEST_ID))->toBeNull();
});

it('never leaks the key from the friend endpoints', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    foreach ([
        fn () => friendsClient()->friendIds(FRIENDS_TEST_ID),
        fn () => friendsClient()->recentlyPlayedAppIds(FRIENDS_TEST_ID),
    ] as $call) {
        try {
            $call();
            $this->fail('Expected an exception.');
        } catch (SteamApiException $e) {
            expect($e->getMessage())->not->toContain(FRIENDS_TEST_KEY)
                ->and($e->getPrevious())->toBeNull()
                ->and((string) $e)->not->toContain(FRIENDS_TEST_KEY);
        }
    }

    Http::fake(function (Request $request) {
        throw new ConnectionException('cURL error 28 for '.$request->url());
    });

    try {
        friendsClient()->friendIds(FRIENDS_TEST_ID);
        $this->fail('Expected an exception.');
    } catch (SteamApiException $e) {
        expect((string) $e)->not->toContain(FRIENDS_TEST_KEY)->and($e->getPrevious())->toBeNull();
    }
});
