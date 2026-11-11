<?php

use App\Jobs\MatchGames;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

// fakeSteam() and runImport() come from ImportLibraryTest.php.

beforeEach(function () {
    config(['services.steam.key' => 'TESTKEY0123456789ABCDEF']);
    Http::preventStrayRequests();
    Queue::fake();
});

it('dispatches MatchGames after a public import when igdb is configured', function () {
    config(['services.igdb.client_id' => 'test-id', 'services.igdb.client_secret' => 'test-secret']);
    fakeSteam();

    runImport(User::factory()->create());

    Queue::assertPushed(MatchGames::class, 1);
});

it('does not dispatch MatchGames when igdb is not configured', function () {
    fakeSteam();

    runImport(User::factory()->create());

    Queue::assertNotPushed(MatchGames::class);
});

it('does not dispatch MatchGames for a private library', function () {
    config(['services.igdb.client_id' => 'test-id', 'services.igdb.client_secret' => 'test-secret']);
    fakeSteam('owned_games_private');

    runImport(User::factory()->create());

    Queue::assertNotPushed(MatchGames::class);
});
