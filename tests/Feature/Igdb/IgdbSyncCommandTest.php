<?php

use App\Jobs\MatchGames;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

it('dispatches MatchGames when igdb is configured', function () {
    config(['services.igdb.client_id' => 'test-id', 'services.igdb.client_secret' => 'test-secret']);
    Queue::fake();

    $this->artisan('igdb:sync')->expectsOutput('Dispatched MatchGames.')->assertSuccessful();

    Queue::assertPushed(MatchGames::class, 1);
});

it('skips when igdb is not configured', function () {
    Queue::fake();

    $this->artisan('igdb:sync')->expectsOutput('IGDB is not configured; skipped.')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('is scheduled hourly without overlap', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $e) => str_contains($e->command ?? '', 'igdb:sync'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
