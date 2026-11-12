<?php

use App\Console\Commands\QueueNightlyImports;
use App\Enums\ImportStatus;
use App\Jobs\ImportLibrary;
use App\Jobs\RefreshFriendActivity;
use App\Models\FriendActivity;
use App\Models\User;
use App\Steam\SteamApiException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\Support\SteamFixtures;

beforeEach(function () {
    config(['services.steam.key' => 'TESTKEY0123456789ABCDEF']);
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
});

/**
 * @param  list<string>  $friends
 * @return array<string, mixed>
 */
function friendsList(array $friends): array
{
    return ['friendslist' => ['friends' => array_map(
        fn (string $id) => ['steamid' => $id, 'relationship' => 'friend', 'friend_since' => 1],
        $friends,
    )]];
}

/** @return array<string, mixed> */
function playedBody(int ...$appIds): array
{
    return ['response' => [
        'total_count' => count($appIds),
        'games' => array_map(fn (int $id) => ['appid' => $id, 'name' => 'x'], $appIds),
    ]];
}

/**
 * @param  list<string>  $friends
 * @param  array<string, array<array-key, mixed>>  $byFriend  friend id => recently played body; others are private
 */
function fakeFriends(array $friends, array $byFriend): void
{
    Http::fake([
        '*GetFriendList*' => Http::response(friendsList($friends)),
        '*GetRecentlyPlayedGames*' => function (Request $request) use ($byFriend) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response($byFriend[$query['steamid']] ?? SteamFixtures::load('recently_played_private'));
        },
    ]);
}

function refresh(User $user): void
{
    app()->call([new RefreshFriendActivity($user), 'handle']);
}

function seedActivity(User $user, int $appId = 999, int $count = 5): void
{
    FriendActivity::create(['user_id' => $user->id, 'steam_appid' => $appId, 'friends_played' => $count, 'captured_on' => '2026-11-01']);
}

it('aggregates how many friends played each game', function () {
    $user = User::factory()->create();
    fakeFriends(['1001', '1002', '1003'], [
        '1001' => playedBody(440, 570),
        '1002' => playedBody(440),
        '1003' => playedBody(440, 620),
    ]);

    refresh($user);

    expect(FriendActivity::where('user_id', $user->id)->pluck('friends_played', 'steam_appid')->all())
        ->toBe([440 => 3, 570 => 1, 620 => 1])
        ->and(FriendActivity::firstOrFail()->captured_on->toDateString())->toBe('2026-11-12');
});

it('skips private friends', function () {
    $user = User::factory()->create();
    fakeFriends(['1001', '1002'], ['1001' => playedBody(440)]);

    refresh($user);

    expect(FriendActivity::pluck('friends_played', 'steam_appid')->all())->toBe([440 => 1]);
});

it('stores nothing and clears old rows when the friends list is private', function () {
    $user = User::factory()->create();
    seedActivity($user, 440, 2);
    Http::fake(['*GetFriendList*' => Http::response('', 401)]);

    refresh($user);

    expect(FriendActivity::count())->toBe(0);
    Http::assertSentCount(1);
});

it('asks about at most 50 friends', function () {
    $user = User::factory()->create();
    $ids = array_map(fn (int $i) => (string) (76561198000000000 + $i), range(1, 60));
    fakeFriends($ids, array_fill_keys($ids, playedBody(440)));

    refresh($user);

    Http::assertSentCount(1 + RefreshFriendActivity::MAX_FRIENDS);
    expect(FriendActivity::where('steam_appid', 440)->value('friends_played'))->toBe(50);
});

it('replaces the user\'s old rows and leaves other users alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    seedActivity($user);
    seedActivity($other);
    fakeFriends(['1001'], ['1001' => playedBody(440)]);

    refresh($user);

    expect(FriendActivity::where('user_id', $user->id)->pluck('friends_played', 'steam_appid')->all())->toBe([440 => 1])
        ->and(FriendActivity::where('user_id', $other->id)->count())->toBe(1);
});

it('stores counts only, with no friend identity columns', function () {
    expect(Schema::getColumnListing('friend_activity'))
        ->toBe(['id', 'user_id', 'steam_appid', 'friends_played', 'captured_on']);

    $user = User::factory()->create();
    fakeFriends(['76561198000000001'], ['76561198000000001' => playedBody(440)]);
    refresh($user);

    expect(json_encode(FriendActivity::all()->toArray()))->not->toContain('7656119800');
});

it('logs a failure without any friend id', function () {
    $user = User::factory()->create();
    $logged = null;
    Log::shouldReceive('warning')->once()->andReturnUsing(function ($message, $context) use (&$logged) {
        $logged = json_encode($context);
    });

    (new RefreshFriendActivity($user))->failed(SteamApiException::http('GetRecentlyPlayedGames', 500));

    expect($logged)->toContain('GetRecentlyPlayedGames')->and($logged)->not->toContain('7656119800');
});

it('releases on a 429 using Retry-After and stores nothing', function () {
    $user = User::factory()->create();
    seedActivity($user);
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '30'])]);

    $job = (new RefreshFriendActivity($user))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(delay: 30);
    expect(FriendActivity::where('steam_appid', 999)->count())->toBe(1);
});

it('releases for 60 seconds on a 429 without Retry-After', function () {
    $user = User::factory()->create();
    Http::fake(['*' => Http::response('', 429)]);

    $job = (new RefreshFriendActivity($user))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(delay: 60);
});

it('is unique per user and rate limited', function () {
    $user = User::factory()->create();
    $job = new RefreshFriendActivity($user);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe((string) $user->id)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

it('is queued by the nightly command for every user', function () {
    Queue::fake();
    $users = User::factory()->count(3)->sequence(
        ['import_status' => ImportStatus::Done],
        ['import_status' => ImportStatus::Queued],
        ['import_status' => ImportStatus::Private],
    )->create();

    $this->artisan(QueueNightlyImports::class)->assertSuccessful();

    Queue::assertPushed(RefreshFriendActivity::class, 3);
    foreach ($users as $user) {
        Queue::assertPushed(RefreshFriendActivity::class, fn (RefreshFriendActivity $job) => $job->user->is($user));
    }
});

it('is queued after a first successful import, but not when activity already exists', function () {
    Queue::fake();
    $new = User::factory()->create();
    $known = User::factory()->create();
    seedActivity($known, 440, 1);
    Http::fake([
        '*GetPlayerSummaries*' => Http::response(SteamFixtures::load('player_summaries_public')),
        '*GetOwnedGames*' => Http::response(SteamFixtures::load('owned_games_public')),
    ]);

    app()->call([new ImportLibrary($new), 'handle']);
    app()->call([new ImportLibrary($known), 'handle']);

    Queue::assertPushed(RefreshFriendActivity::class, 1);
    Queue::assertPushed(RefreshFriendActivity::class, fn (RefreshFriendActivity $job) => $job->user->is($new));
});
