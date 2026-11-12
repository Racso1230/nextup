<?php

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Jobs\ImportLibrary;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use App\Steam\SteamApiException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\SteamFixtures;

const IMPORT_KEY = 'TESTKEY0123456789ABCDEF';

function fakeSteam(string $owned = 'owned_games_public', string $summary = 'player_summaries_public'): void
{
    Http::fake([
        '*GetFriendList*' => Http::response('', 401),
        '*GetPlayerSummaries*' => Http::response(SteamFixtures::load($summary)),
        '*GetOwnedGames*' => Http::response(SteamFixtures::load($owned)),
    ]);
}

function runImport(User $user): void
{
    app()->call([new ImportLibrary($user), 'handle']);
}

beforeEach(function () {
    config(['services.steam.key' => IMPORT_KEY]);
    Http::preventStrayRequests();
});

it('imports a public library', function () {
    $user = User::factory()->create();
    fakeSteam();

    runImport($user);

    $user->refresh();
    expect($user->import_status)->toBe(ImportStatus::Done)
        ->and($user->library_visibility)->toBe(LibraryVisibility::Public)
        ->and($user->imported_at)->not->toBeNull()
        ->and($user->persona_name)->toBe('Example Player')
        ->and($user->avatar_url)->toEndWith('_full.jpg')
        ->and($user->profile_url)->toBe('https://steamcommunity.com/id/exampleplayer/')
        // 9 raw entries: one duplicate appid, one without an appid.
        ->and(Game::count())->toBe(7)
        ->and(LibraryEntry::count())->toBe(7);

    $tf2 = LibraryEntry::whereRelation('game', 'steam_appid', 440)->firstOrFail();
    expect($tf2->playtime_minutes)->toBe(4500)
        ->and($tf2->playtime_2weeks_minutes)->toBe(120)
        ->and($tf2->last_played_at?->timestamp)->toBe(1730000000);

    $dota = LibraryEntry::whereRelation('game', 'steam_appid', 570)->firstOrFail();
    expect($dota->playtime_minutes)->toBe(90)
        ->and($dota->playtime_2weeks_minutes)->toBeNull();

    $portal = LibraryEntry::whereRelation('game', 'steam_appid', 400)->firstOrFail();
    expect($portal->last_played_at)->toBeNull()
        ->and($portal->playtime_minutes)->toBe(0);

    expect(Game::where('steam_appid', 570)->value('name'))->toBe('Dota 2');
});

it('upserts on a re-run without duplicates and updates playtime', function () {
    $user = User::factory()->create();
    $body = SteamFixtures::load('owned_games_public');
    Http::fake([
        '*GetFriendList*' => Http::response('', 401),
        '*GetPlayerSummaries*' => Http::response(SteamFixtures::load('player_summaries_public')),
        '*GetOwnedGames*' => function () use (&$body) {
            return Http::response($body);
        },
    ]);
    runImport($user);

    $body['response']['games'][0]['playtime_forever'] = 4600;
    runImport($user);

    expect(Game::count())->toBe(7)
        ->and(LibraryEntry::count())->toBe(7)
        ->and(LibraryEntry::whereRelation('game', 'steam_appid', 440)->value('playtime_minutes'))->toBe(4600);
});

it('shares one games row between users who own the same appid', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    fakeSteam();

    runImport($a);
    runImport($b);

    expect(Game::count())->toBe(7)
        ->and(LibraryEntry::count())->toBe(14)
        ->and(LibraryEntry::where('user_id', $a->id)->count())->toBe(7);
});

it('keeps entries for games that left the owned list', function () {
    $user = User::factory()->create();
    $game = Game::factory()->create(['steam_appid' => 999999]);
    LibraryEntry::factory()->create(['user_id' => $user->id, 'game_id' => $game->id]);
    fakeSteam();

    runImport($user);

    expect(LibraryEntry::where('user_id', $user->id)->count())->toBe(8);
});

it('marks a private library private and keeps existing entries', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->create(['user_id' => $user->id]);
    fakeSteam('owned_games_private');

    runImport($user);

    $user->refresh();
    expect($user->library_visibility)->toBe(LibraryVisibility::Private)
        ->and($user->import_status)->toBe(ImportStatus::Private)
        ->and(LibraryEntry::where('user_id', $user->id)->count())->toBe(1);
});

it('treats an empty public library as done with no entries', function () {
    $user = User::factory()->create();
    fakeSteam('owned_games_empty');

    runImport($user);

    $user->refresh();
    expect($user->import_status)->toBe(ImportStatus::Done)
        ->and($user->library_visibility)->toBe(LibraryVisibility::Public)
        ->and($user->imported_at)->not->toBeNull()
        ->and(LibraryEntry::count())->toBe(0);
});

it('releases the job using the Retry-After header on a 429', function () {
    $user = User::factory()->create();
    Http::fake(['*' => Http::response('', 429, ['Retry-After' => '30'])]);

    $job = (new ImportLibrary($user))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(delay: 30);
    expect($user->fresh()?->import_status)->toBe(ImportStatus::Queued);
});

it('releases for 60 seconds on a 429 without Retry-After', function () {
    $user = User::factory()->create();
    Http::fake(['*' => Http::response('', 429)]);

    $job = (new ImportLibrary($user))->withFakeQueueInteractions();
    app()->call([$job, 'handle']);

    $job->assertReleased(delay: 60);
});

it('rethrows other Steam errors so the queue retries', function () {
    $user = User::factory()->create();
    Http::fake(['*' => Http::response('', 500)]);

    expect(fn () => runImport($user))->toThrow(SteamApiException::class);
    expect($user->refresh()->import_status)->toBe(ImportStatus::Running);
});

it('sets failed and logs only safe context when the job fails', function () {
    $user = User::factory()->create();
    Log::spy();

    (new ImportLibrary($user))->failed(SteamApiException::http('GetOwnedGames', 500));

    expect($user->refresh()->import_status)->toBe(ImportStatus::Failed);
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $context === [
            'user_id' => $user->id,
            'exception' => SteamApiException::class,
            'endpoint' => 'GetOwnedGames',
            'status' => 500,
        ] && ! str_contains(json_encode($context) ?: '', IMPORT_KEY),
    );
});

it('never logs the message of a non-Steam exception', function () {
    $user = User::factory()->create();
    Log::spy();

    (new ImportLibrary($user))->failed(new RuntimeException('boom https://api.steampowered.com/?key='.IMPORT_KEY));

    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $context === ['user_id' => $user->id, 'exception' => RuntimeException::class]
            && ! str_contains(json_encode($context) ?: '', IMPORT_KEY),
    );
});

it('is unique per user, queued, retried with backoff and rate limited', function () {
    $user = User::factory()->create();
    $job = new ImportLibrary($user);

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->uniqueId())->toBe((string) $user->id)
        ->and($job->tries)->toBe(4)
        ->and($job->backoff())->toBe([10, 30, 90])
        ->and($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and(RateLimiter::limiter('steam'))->not->toBeNull();
});
