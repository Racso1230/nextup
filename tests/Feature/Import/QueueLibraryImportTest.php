<?php

use App\Enums\ImportStatus;
use App\Jobs\ImportLibrary;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

test('login queues an import when the library was never imported', function () {
    $user = User::factory()->create(['steam_id' => STEAM_ID, 'imported_at' => null]);
    fakeSteamProvider(steamUser());

    $this->get(route('auth.steam.callback'))->assertRedirect(route('library'));

    Queue::assertPushed(ImportLibrary::class, fn (ImportLibrary $job) => $job->user->is($user));
    expect($user->refresh()->import_status)->toBe(ImportStatus::Queued);
});

test('login queues an import when the last one is older than six hours', function () {
    User::factory()->create(['steam_id' => STEAM_ID, 'imported_at' => now()->subHours(7)]);
    fakeSteamProvider(steamUser());

    $this->get(route('auth.steam.callback'));

    Queue::assertPushed(ImportLibrary::class);
});

test('login does not queue an import when the last one was an hour ago', function () {
    User::factory()->create(['steam_id' => STEAM_ID, 'imported_at' => now()->subHour()]);
    fakeSteamProvider(steamUser());

    $this->get(route('auth.steam.callback'))->assertRedirect(route('library'));

    Queue::assertNothingPushed();
});

test('posting to library.import queues an import and shows a toast', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('library'))
        ->post(route('library.import'))
        ->assertRedirect(route('library'))
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Importing your library.']);

    Queue::assertPushed(ImportLibrary::class, 1);
    expect($user->refresh()->import_status)->toBe(ImportStatus::Queued);
});

test('posting while an import is queued or running does nothing extra', function (ImportStatus $status) {
    $user = User::factory()->create(['import_status' => $status]);

    $this->actingAs($user)->post(route('library.import'))->assertRedirect();

    Queue::assertNothingPushed();
    expect($user->refresh()->import_status)->toBe($status);
})->with([ImportStatus::Queued, ImportStatus::Running]);

test('guests cannot queue an import', function () {
    $this->post(route('library.import'))->assertRedirect(route('login'));

    Queue::assertNothingPushed();
});

test('the import route is throttled to three a minute', function () {
    $user = User::factory()->create();

    foreach (range(1, 3) as $_) {
        $this->actingAs($user)->post(route('library.import'))->assertRedirect();
    }

    $this->actingAs($user)->post(route('library.import'))->assertTooManyRequests();
});
