<?php

use App\Enums\ImportStatus;
use App\Jobs\ImportLibrary;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

it('queues imports for every user not already queued or running', function () {
    Queue::fake();

    $eligible = collect([ImportStatus::Idle, ImportStatus::Done, ImportStatus::Private, ImportStatus::Failed])
        ->map(fn (ImportStatus $s) => User::factory()->create(['import_status' => $s]));
    User::factory()->create(['import_status' => ImportStatus::Queued]);
    User::factory()->create(['import_status' => ImportStatus::Running]);

    $this->artisan('library:queue-nightly')
        ->expectsOutput('Queued 4 library imports.')
        ->assertSuccessful();

    Queue::assertPushed(ImportLibrary::class, 4);
    foreach ($eligible as $user) {
        Queue::assertPushed(ImportLibrary::class, fn (ImportLibrary $job) => $job->user->is($user));
        expect($user->fresh()->import_status)->toBe(ImportStatus::Queued);
    }
});

it('schedules the nightly command at 03:00 UTC', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'library:queue-nightly'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 3 * * *')
        ->and($event->timezone)->toBe('UTC')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
