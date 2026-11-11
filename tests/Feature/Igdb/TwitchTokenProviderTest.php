<?php

use App\Igdb\IgdbApiException;
use App\Igdb\TwitchTokenProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\IgdbFixtures;

const TOKEN_TEST_ID = 'fake-client-id-123';
const TOKEN_TEST_SECRET = 'fake-client-secret-456';

function tokenProvider(string $id = TOKEN_TEST_ID, string $secret = TOKEN_TEST_SECRET): TwitchTokenProvider
{
    return new TwitchTokenProvider($id, $secret, app(Factory::class), app(Repository::class));
}

it('requests a client credentials token with the secret in the POST body only', function () {
    Http::fake(['id.twitch.tv/*' => Http::response(IgdbFixtures::load('token'))]);

    expect(tokenProvider()->token())->toBe('fixturetoken0123456789abcdefghij');

    Http::assertSent(function (Request $request) {
        parse_str($request->body(), $body);

        return $request->method() === 'POST'
            && $request->url() === 'https://id.twitch.tv/oauth2/token'
            && $body === [
                'client_id' => TOKEN_TEST_ID,
                'client_secret' => TOKEN_TEST_SECRET,
                'grant_type' => 'client_credentials',
            ];
    });
});

it('caches the token so later calls do not hit Twitch again', function () {
    Http::fake(['id.twitch.tv/*' => Http::response(IgdbFixtures::load('token'))]);

    $provider = tokenProvider();
    $provider->token();
    $provider->token();
    tokenProvider()->token();

    Http::assertSentCount(1);
    expect(Cache::get(TwitchTokenProvider::CACHE_KEY))->toBe('fixturetoken0123456789abcdefghij');
});

it('fetches a new token after forget', function () {
    Http::fake(['id.twitch.tv/*' => Http::sequence()
        ->push(['access_token' => 'first', 'expires_in' => 3600])
        ->push(['access_token' => 'second', 'expires_in' => 3600]),
    ]);

    $provider = tokenProvider();

    expect($provider->token())->toBe('first');

    $provider->forget();

    expect(Cache::has(TwitchTokenProvider::CACHE_KEY))->toBeFalse()
        ->and($provider->token())->toBe('second');
});

it('computes the ttl with a margin of five minutes, or ten percent for short tokens', function (int $expiresIn, int $ttl) {
    expect(TwitchTokenProvider::ttl($expiresIn))->toBe($ttl);
})->with([
    'long token' => [5587808, 5587508],
    'exactly one hour' => [3600, 3300],
    'short token' => [100, 90],
    'tiny token' => [5, 5],
    'one second' => [1, 1],
]);

it('expires the cached token at the margin', function () {
    Http::fake(['id.twitch.tv/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600])]);

    tokenProvider()->token();

    $this->travel(3299)->seconds();
    expect(Cache::has(TwitchTokenProvider::CACHE_KEY))->toBeTrue();

    $this->travel(3)->seconds();
    expect(Cache::has(TwitchTokenProvider::CACHE_KEY))->toBeFalse();
});

it('throws without credentials and makes no request', function () {
    Http::fake();

    expect(fn () => tokenProvider('', TOKEN_TEST_SECRET)->token())->toThrow(IgdbApiException::class)
        ->and(fn () => tokenProvider(TOKEN_TEST_ID, '')->token())->toThrow(IgdbApiException::class);

    Http::assertNothingSent();
});

it('rethrows failures without the secret anywhere', function (callable $fake) {
    Http::fake(['id.twitch.tv/*' => $fake]);

    try {
        tokenProvider()->token();
        $this->fail('Expected an IgdbApiException.');
    } catch (IgdbApiException $e) {
        $everything = $e->getMessage().(string) $e;

        expect($e->getPrevious())->toBeNull()
            ->and($everything)->not->toContain(TOKEN_TEST_SECRET)
            ->and($everything)->not->toContain(TOKEN_TEST_ID);
    }
})->with([
    'http 403' => [fn () => Http::response(['status' => 403, 'message' => 'invalid client secret'], 403)],
    'connection failure' => [fn () => throw new ConnectionException('cURL error for '.TOKEN_TEST_SECRET)],
    'no token in body' => [fn () => Http::response(['expires_in' => 3600])],
    'no expiry in body' => [fn () => Http::response(['access_token' => 'tok'])],
]);

it('defines an igdb rate limiter of about three requests a second', function () {
    $limiter = RateLimiter::limiter('igdb');

    expect($limiter)->not->toBeNull();

    $limit = $limiter(new stdClass);

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(3)
        ->and($limit->decaySeconds)->toBe(1);
});
