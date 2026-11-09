<?php

use App\Steam\SteamApiException;
use App\Steam\SteamClient;
use App\Steam\SteamRateLimitedException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\SteamFixtures;

const TEST_KEY = 'TESTKEY0123456789ABCDEF';
const TEST_STEAM_ID = '76561197960287930';

function steamClient(string $key = TEST_KEY): SteamClient
{
    return new SteamClient($key, app(Factory::class));
}

beforeEach(function () {
    Http::preventStrayRequests();
});

it('reads a public library', function () {
    Http::fake(['api.steampowered.com/*' => Http::response(SteamFixtures::load('owned_games_public'))]);

    $result = steamClient()->ownedGames(TEST_STEAM_ID);

    expect($result->isPrivate)->toBeFalse()
        ->and($result->games)->toHaveCount(7);
});

it('reports a private library', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('owned_games_private'))]);

    expect(steamClient()->ownedGames(TEST_STEAM_ID)->isPrivate)->toBeTrue();
});

it('reports an empty public library as not private', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('owned_games_empty'))]);

    $result = steamClient()->ownedGames(TEST_STEAM_ID);

    expect($result->isPrivate)->toBeFalse()
        ->and($result->games)->toBe([]);
});

it('sends owned games requests over https with the expected params', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('owned_games_empty'))]);

    steamClient()->ownedGames(TEST_STEAM_ID);

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?')
            && $query['key'] === TEST_KEY
            && $query['steamid'] === TEST_STEAM_ID
            && $query['include_appinfo'] === '1'
            && $query['include_played_free_games'] === '1'
            && $request->hasHeader('Accept', 'application/json');
    });
});

it('reads a player summary', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('player_summaries_public'))]);

    $summary = steamClient()->playerSummary(TEST_STEAM_ID);

    expect($summary?->personaName)->toBe('Example Player')
        ->and($summary->isPublic)->toBeTrue();

    Http::assertSent(function (Request $request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_starts_with($request->url(), 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?')
            && $query['steamids'] === TEST_STEAM_ID
            && $query['key'] === TEST_KEY;
    });
});

it('reads a private player summary', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('player_summaries_private'))]);

    expect(steamClient()->playerSummary(TEST_STEAM_ID)?->isPublic)->toBeFalse();
});

it('returns null when the player is not found', function () {
    Http::fake(['*' => Http::response(SteamFixtures::load('player_summaries_not_found'))]);

    expect(steamClient()->playerSummary(TEST_STEAM_ID))->toBeNull();
});

it('maps 429 to a rate limit exception with Retry-After', function () {
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '30'])]);

    try {
        steamClient()->ownedGames(TEST_STEAM_ID);
        $this->fail('Expected a rate limit exception.');
    } catch (SteamRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBe(30)
            ->and($e->status)->toBe(429)
            ->and($e->endpoint)->toBe('GetOwnedGames');
    }
});

it('leaves retryAfterSeconds null without a usable header', function () {
    Http::fake(['*' => Http::response('', 429)]);

    try {
        steamClient()->playerSummary(TEST_STEAM_ID);
        $this->fail('Expected a rate limit exception.');
    } catch (SteamRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBeNull()
            ->and($e->endpoint)->toBe('GetPlayerSummaries');
    }
});

it('maps other 4xx and 5xx statuses to SteamApiException', function (int $status) {
    Http::fake(['*' => Http::response('nope', $status)]);

    try {
        steamClient()->ownedGames(TEST_STEAM_ID);
        $this->fail('Expected an exception.');
    } catch (SteamApiException $e) {
        expect($e)->not->toBeInstanceOf(SteamRateLimitedException::class)
            ->and($e->status)->toBe($status)
            ->and($e->endpoint)->toBe('GetOwnedGames')
            ->and($e->getMessage())->toContain((string) $status);
    }
})->with([401, 403, 500, 503]);

it('throws on a body that is not JSON', function () {
    Http::fake(['*' => Http::response('<html>oops</html>', 200)]);

    expect(fn () => steamClient()->ownedGames(TEST_STEAM_ID))->toThrow(SteamApiException::class, 'unexpected response');
});

it('never leaks the key through an HTTP error', function () {
    Http::fake(['*' => Http::response('boom', 500)]);

    try {
        steamClient()->ownedGames(TEST_STEAM_ID);
        $this->fail('Expected an exception.');
    } catch (SteamApiException $e) {
        expect($e->getMessage())->not->toContain(TEST_KEY)
            ->and($e->getPrevious())->toBeNull()
            ->and((string) $e)->not->toContain(TEST_KEY);
    }
});

it('maps a connection failure without leaking the key', function () {
    Http::fake(function (Request $request) {
        throw new ConnectionException('cURL error 28 for '.$request->url());
    });

    try {
        steamClient()->ownedGames(TEST_STEAM_ID);
        $this->fail('Expected an exception.');
    } catch (SteamApiException $e) {
        expect($e->getMessage())->toContain('connection failed')
            ->and($e->getMessage())->not->toContain(TEST_KEY)
            ->and($e->getPrevious())->toBeNull()
            ->and((string) $e)->not->toContain(TEST_KEY);
    }
});

it('throws on first use, not at construction, when the key is missing', function () {
    Http::fake();

    $client = steamClient('');

    expect(fn () => $client->ownedGames(TEST_STEAM_ID))->toThrow(SteamApiException::class, 'no Steam API key')
        ->and(fn () => $client->playerSummary(TEST_STEAM_ID))->toThrow(SteamApiException::class);

    Http::assertNothingSent();
});

it('refuses a non-https base URL', function () {
    new SteamClient(TEST_KEY, app(Factory::class), 'http://api.steampowered.com');
})->throws(InvalidArgumentException::class);

it('resolves from the container using the configured key', function () {
    config(['services.steam.key' => TEST_KEY]);
    Http::fake(['*' => Http::response(SteamFixtures::load('owned_games_empty'))]);

    app(SteamClient::class)->ownedGames(TEST_STEAM_ID);

    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'key='.TEST_KEY));
});
