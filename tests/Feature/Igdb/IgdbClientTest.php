<?php

use App\Enums\TagType;
use App\Igdb\IgdbApiException;
use App\Igdb\IgdbClient;
use App\Igdb\IgdbRateLimitedException;
use App\Igdb\TwitchTokenProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\IgdbFixtures;

const IGDB_TEST_ID = 'fake-client-id-123';
const IGDB_TEST_SECRET = 'fake-client-secret-456';
const IGDB_TEST_TOKEN = 'fixturetoken0123456789abcdefghij';

function igdbClient(string $id = IGDB_TEST_ID, string $secret = IGDB_TEST_SECRET): IgdbClient
{
    $tokens = new TwitchTokenProvider($id, $secret, app(Factory::class), app(Repository::class));

    return new IgdbClient($id, $secret, $tokens, app(Factory::class));
}

/** Fakes Twitch with the fixture token and IGDB with the given response (or a callable / sequence). */
function fakeIgdb(mixed $igdb): void
{
    Http::fake([
        'id.twitch.tv/*' => Http::response(IgdbFixtures::load('token')),
        'api.igdb.com/*' => $igdb,
    ]);
}

function igdbRequests(): array
{
    return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://api.igdb.com'))
        ->map(fn (array $pair) => $pair[0])
        ->values()
        ->all();
}

it('maps steam appids to igdb ids', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('external_games_steam')));

    $map = igdbClient()->steamGameIds([620, 413150, 1145360, 99999999]);

    expect($map)->toBe([620 => 72, 413150 => 17000, 1145360 => 113112]);
});

it('queries external_games with the steam source, the quoted appids and a limit', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('empty')));

    igdbClient()->steamGameIds([620, 413150]);

    Http::assertSent(function (Request $request) {
        if (! str_starts_with($request->url(), 'https://api.igdb.com')) {
            return false;
        }

        return $request->method() === 'POST'
            && $request->url() === 'https://api.igdb.com/v4/external_games'
            && $request->hasHeader('Client-ID', IGDB_TEST_ID)
            && $request->hasHeader('Authorization', 'Bearer '.IGDB_TEST_TOKEN)
            && str_starts_with($request->header('Content-Type')[0], 'text/plain')
            && $request->body() === 'fields uid,game; where external_game_source = 1 & uid = ("620","413150"); sort id asc; limit 500; offset 0;';
    });
});

it('ignores malformed external game rows', function () {
    fakeIgdb(Http::response([
        'junk',
        ['uid' => '620'],
        ['uid' => 'abc', 'game' => 5],
        ['uid' => '0', 'game' => 5],
        ['uid' => 730, 'game' => 5],
        ['uid' => '730', 'game' => -1],
        ['uid' => '413150', 'game' => 17000],
        ['uid' => '413150', 'game' => 99],
    ]));

    expect(igdbClient()->steamGameIds([620, 730, 413150]))->toBe([413150 => 17000]);
});

it('fetches games with expanded tag names', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('games')));

    $games = igdbClient()->games([72, 113112, 17000]);

    expect($games)->toHaveCount(3)
        ->and($games[0]->name)->toBe('Portal 2')
        ->and($games[0]->coverImageId)->toBe('co1rs4')
        ->and($games[0]->similarIgdbIds)->toBe([71, 1030, 7346])
        ->and($games[0]->firstReleaseDate?->toDateString())->toBe('2011-04-19')
        ->and($games[2]->coverImageId)->toBeNull()
        ->and(collect($games[2]->tags)->where('type', TagType::Keyword))->toBeEmpty()
        ->and(collect($games[0]->tags)->where('type', TagType::Keyword))->toHaveCount(2);

    Http::assertSent(function (Request $request) {
        $body = $request->body();

        return $request->url() === 'https://api.igdb.com/v4/games'
            && str_contains($body, 'genres.name')
            && str_contains($body, 'themes.name')
            && str_contains($body, 'game_modes.name')
            && str_contains($body, 'player_perspectives.name')
            && str_contains($body, 'keywords.name')
            && str_contains($body, 'cover.image_id')
            && str_contains($body, 'similar_games')
            && str_contains($body, 'first_release_date')
            && str_ends_with($body, 'where id = (72,113112,17000); limit 500;');
    });
});

it('fetches times to beat keyed by game id, leaving out games without data', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('time_to_beats')));

    $times = igdbClient()->timesToBeat([72, 113112, 17000]);

    expect($times)->toHaveCount(2)
        ->and($times[72]->hastily)->toBe(29700)
        ->and($times[72]->normally)->toBe(36000)
        ->and($times[72]->completely)->toBe(90000)
        ->and($times[113112]->hastily)->toBeNull()
        ->and($times[113112]->normally)->toBe(79200)
        ->and($times[113112]->completely)->toBeNull()
        ->and($times)->not->toHaveKey(17000);

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.igdb.com/v4/game_time_to_beats'
        && $request->body() === 'fields game_id,hastily,normally,completely; where game_id = (72,113112,17000); sort id asc; limit 500; offset 0;');
});

it('makes no request for empty input', function () {
    Http::fake();

    $client = igdbClient();

    expect($client->steamGameIds([]))->toBe([])
        ->and($client->games([]))->toBe([])
        ->and($client->timesToBeat([]))->toBe([]);

    Http::assertNothingSent();
});

it('splits 1200 ids into three requests of at most 500', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('empty')));

    igdbClient()->games(range(1, 1200));

    $requests = igdbRequests();

    expect($requests)->toHaveCount(3);

    $counts = array_map(function (Request $request) {
        preg_match('/where id = \(([\d,]+)\); limit 500;$/', $request->body(), $m);

        return count(explode(',', $m[1]));
    }, $requests);

    expect($counts)->toBe([500, 500, 200]);
});

it('deduplicates ids before chunking', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('empty')));

    igdbClient()->timesToBeat([5, 5, 6]);

    Http::assertSent(fn (Request $request) => str_contains($request->body(), 'game_id = (5,6);'));
});

it('rejects invalid ids before sending anything', function (array $ids) {
    Http::fake();

    expect(fn () => igdbClient()->games($ids))->toThrow(InvalidArgumentException::class)
        ->and(fn () => igdbClient()->steamGameIds($ids))->toThrow(InvalidArgumentException::class)
        ->and(fn () => igdbClient()->timesToBeat($ids))->toThrow(InvalidArgumentException::class);

    Http::assertNothingSent();
})->with([
    'zero' => [[0]],
    'negative' => [[5, -3]],
    'numeric string' => [['72']],
    'injection' => [['72); delete games; where (1']],
    'float' => [[1.5]],
]);

it('fetches the token once and reuses it across calls', function () {
    fakeIgdb(Http::response(IgdbFixtures::load('empty')));

    $client = igdbClient();
    $client->games([1]);
    $client->timesToBeat([1]);
    $client->steamGameIds([1]);

    $tokenRequests = Http::recorded(fn (Request $request) => str_contains($request->url(), 'id.twitch.tv'));

    expect($tokenRequests)->toHaveCount(1)
        ->and(igdbRequests())->toHaveCount(3)
        ->and(Cache::get(TwitchTokenProvider::CACHE_KEY))->toBe(IGDB_TEST_TOKEN);
});

it('refreshes the token once on a 401 and retries', function () {
    Http::fake([
        'id.twitch.tv/*' => Http::sequence()
            ->push(['access_token' => 'stale-token', 'expires_in' => 3600])
            ->push(['access_token' => 'fresh-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::sequence()
            ->push(['message' => 'Authorization Failure.'], 401)
            ->push(IgdbFixtures::load('time_to_beats')),
    ]);

    $times = igdbClient()->timesToBeat([72, 113112]);

    expect($times)->toHaveCount(2);

    $requests = igdbRequests();

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->hasHeader('Authorization', 'Bearer stale-token'))->toBeTrue()
        ->and($requests[1]->hasHeader('Authorization', 'Bearer fresh-token'))->toBeTrue()
        ->and(Cache::get(TwitchTokenProvider::CACHE_KEY))->toBe('fresh-token');
});

it('throws on a second 401 instead of looping', function () {
    Http::fake([
        'id.twitch.tv/*' => Http::sequence()
            ->push(['access_token' => 'first-token', 'expires_in' => 3600])
            ->push(['access_token' => 'second-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response(['message' => 'Authorization Failure.'], 401),
    ]);

    try {
        igdbClient()->games([1]);
        $this->fail('Expected an IgdbApiException.');
    } catch (IgdbApiException $e) {
        expect($e->status)->toBe(401)
            ->and($e->endpoint)->toBe('games');
    }

    expect(igdbRequests())->toHaveCount(2);
});

it('throws a rate limited exception on 429 and reads Retry-After', function () {
    fakeIgdb(Http::response('Too Many Requests', 429, ['Retry-After' => '7']));

    try {
        igdbClient()->games([1]);
        $this->fail('Expected an IgdbRateLimitedException.');
    } catch (IgdbRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBe(7)
            ->and($e->status)->toBe(429)
            ->and($e->endpoint)->toBe('games');
    }
});

it('leaves retryAfterSeconds null on a 429 without a usable Retry-After', function (array $headers) {
    fakeIgdb(Http::response('Too Many Requests', 429, $headers));

    try {
        igdbClient()->games([1]);
        $this->fail('Expected an IgdbRateLimitedException.');
    } catch (IgdbRateLimitedException $e) {
        expect($e->retryAfterSeconds)->toBeNull();
    }
})->with([
    'missing' => [[]],
    'http date' => [['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT']],
]);

it('throws key-safe errors for server errors and connection failures', function (callable $fake, ?int $status) {
    fakeIgdb($fake);

    try {
        igdbClient()->games([1]);
        $this->fail('Expected an IgdbApiException.');
    } catch (IgdbApiException $e) {
        $everything = $e->getMessage().(string) $e;

        expect($e->getPrevious())->toBeNull()
            ->and($e->status)->toBe($status)
            ->and($e->endpoint)->toBe('games')
            ->and($e->getMessage())->toContain('games');

        foreach ([IGDB_TEST_ID, IGDB_TEST_SECRET, IGDB_TEST_TOKEN, 'api.igdb.com', 'Bearer'] as $secret) {
            expect($everything)->not->toContain($secret);
        }
    }
})->with([
    'http 500' => [fn () => Http::response('boom', 500), 500],
    'http 400' => [fn () => Http::response('bad query', 400), 400],
    'connection failure' => [
        fn () => throw new ConnectionException('cURL error 28 for Bearer '.IGDB_TEST_TOKEN.' Client-ID '.IGDB_TEST_ID.' '.IGDB_TEST_SECRET),
        null,
    ],
]);

it('rejects a non-json success body', function () {
    fakeIgdb(Http::response('<html>nope</html>', 200));

    expect(fn () => igdbClient()->games([1]))->toThrow(IgdbApiException::class, 'unexpected response');
});

it('throws on first use, not at construction, when credentials are missing', function (string $id, string $secret) {
    Http::fake();

    $client = igdbClient($id, $secret);

    expect(fn () => $client->games([1]))->toThrow(IgdbApiException::class, 'credentials are not configured')
        ->and(fn () => $client->steamGameIds([1]))->toThrow(IgdbApiException::class)
        ->and(fn () => $client->timesToBeat([1]))->toThrow(IgdbApiException::class);

    Http::assertNothingSent();
})->with([
    'no id' => ['', IGDB_TEST_SECRET],
    'no secret' => [IGDB_TEST_ID, ''],
    'neither' => ['', ''],
]);

it('is resolvable from the container with blank credentials', function () {
    config(['services.igdb.client_id' => null, 'services.igdb.client_secret' => null]);

    $client = app(IgdbClient::class);

    expect($client)->toBeInstanceOf(IgdbClient::class)
        ->and(fn () => $client->games([1]))->toThrow(IgdbApiException::class);
});

it('refuses a non-https base url', function () {
    $tokens = new TwitchTokenProvider('a', 'b', app(Factory::class), app(Repository::class));

    new IgdbClient('a', 'b', $tokens, app(Factory::class), 'http://api.igdb.com/v4');
})->throws(InvalidArgumentException::class);

it('pages external_games when a full page comes back and keeps the first game id per appid', function () {
    $page1 = [['uid' => '620', 'game' => 72]];

    for ($i = 1; $i < 500; $i++) {
        $page1[] = ['uid' => '620', 'game' => 1000 + $i]; // duplicate rows for the same appid
    }

    fakeIgdb(Http::sequence()
        ->push($page1)
        ->push([['uid' => '620', 'game' => 5], ['uid' => '730', 'game' => 9]]));

    $map = igdbClient()->steamGameIds([620, 730]);

    expect($map)->toBe([620 => 72, 730 => 9]);

    $requests = igdbRequests();

    expect($requests)->toHaveCount(2)
        ->and($requests[0]->body())->toEndWith('sort id asc; limit 500; offset 0;')
        ->and($requests[1]->body())->toEndWith('sort id asc; limit 500; offset 500;');
});

it('pages game_time_to_beats the same way', function () {
    $page1 = [];

    for ($i = 1; $i <= 500; $i++) {
        $page1[] = ['game_id' => $i, 'normally' => 100];
    }

    fakeIgdb(Http::sequence()->push($page1)->push([['game_id' => 501, 'normally' => 200]]));

    $times = igdbClient()->timesToBeat([1, 501]);

    expect($times)->toHaveCount(501)
        ->and($times[501]->normally)->toBe(200)
        ->and(igdbRequests())->toHaveCount(2)
        ->and(igdbRequests()[1]->body())->toEndWith('offset 500;');
});

it('does not request an extra page after a short page', function () {
    fakeIgdb(Http::response([['uid' => '620', 'game' => 72]]));

    igdbClient()->steamGameIds([620]);

    expect(igdbRequests())->toHaveCount(1);
});

it('throws instead of looping forever when the page cap is hit', function (string $method) {
    $full = array_map(fn (int $i) => ['uid' => (string) $i, 'game' => $i, 'game_id' => $i, 'normally' => 1], range(1, 500));

    fakeIgdb(Http::response($full));

    expect(fn () => igdbClient()->{$method}([1]))->toThrow(IgdbApiException::class, 'too many result pages');
    expect(igdbRequests())->toHaveCount(20);
})->with(['steamGameIds', 'timesToBeat']);
