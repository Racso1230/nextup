<?php

use App\Actions\RecordPlaytimeSnapshots;
use App\Jobs\ImportLibrary;
use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\SteamFixtures;

function snapshotImport(User $user, string $owned = 'owned_games_public'): void
{
    Http::fake([
        '*GetFriendList*' => Http::response('', 401),
        '*GetPlayerSummaries*' => Http::response(SteamFixtures::load('player_summaries_public')),
        '*GetOwnedGames*' => Http::response(SteamFixtures::load($owned)),
    ]);

    app()->call([new ImportLibrary($user), 'handle']);
}

beforeEach(function () {
    config(['services.steam.key' => 'TESTKEY0123456789ABCDEF']);
    Http::preventStrayRequests();
    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:00:00', 'UTC'));
});

it('writes one snapshot per entry on the first import', function () {
    $user = User::factory()->create();

    snapshotImport($user);

    expect(LibraryEntry::count())->toBe(7)
        ->and(PlaytimeSnapshot::count())->toBe(7);

    $tf2 = LibraryEntry::whereRelation('game', 'steam_appid', 440)->firstOrFail();
    $snapshot = $tf2->snapshots()->firstOrFail();
    expect($snapshot->minutes_total)->toBe(4500)
        ->and($snapshot->captured_on->toDateString())->toBe('2026-11-09');
});

it('writes nothing on a same-day re-run with unchanged playtime', function () {
    $user = User::factory()->create();
    snapshotImport($user);

    snapshotImport($user);

    expect(PlaytimeSnapshot::count())->toBe(7);
});

it('writes nothing on a next-day run with unchanged playtime', function () {
    $user = User::factory()->create();
    snapshotImport($user);

    $this->travel(1)->days();
    snapshotImport($user);

    expect(PlaytimeSnapshot::count())->toBe(7);
});

it('writes exactly one row, for the game whose minutes grew, on a next-day run', function () {
    $user = User::factory()->create();
    snapshotImport($user);

    // Make the stored snapshot lag behind Steam's total for one game, as if it had grown overnight.
    $dota = LibraryEntry::whereRelation('game', 'steam_appid', 570)->firstOrFail();
    PlaytimeSnapshot::where('library_entry_id', $dota->id)->update(['minutes_total' => 60]);
    $this->travel(1)->days();

    snapshotImport($user);

    expect(PlaytimeSnapshot::count())->toBe(8);
    $newRows = PlaytimeSnapshot::where('captured_on', '2026-11-10')->get();
    expect($newRows)->toHaveCount(1)
        ->and($newRows->first()?->library_entry_id)->toBe($dota->id)
        ->and($newRows->first()?->minutes_total)->toBe(90);
});

it('records growth for exactly the changed entry', function () {
    $user = User::factory()->create();
    $entries = LibraryEntry::factory()->count(3)->for($user)->create(['playtime_minutes' => 100]);
    $action = app(RecordPlaytimeSnapshots::class);

    expect($action($user, CarbonImmutable::parse('2026-11-09', 'UTC')))->toBe(3);

    $entries[1]->update(['playtime_minutes' => 130]);

    expect($action($user, CarbonImmutable::parse('2026-11-10', 'UTC')))->toBe(1)
        ->and(PlaytimeSnapshot::where('captured_on', '2026-11-10')->pluck('library_entry_id')->all())
        ->toBe([$entries[1]->id]);
});

it("updates today's row when playtime changes later the same day", function () {
    $user = User::factory()->create();
    $entry = LibraryEntry::factory()->for($user)->create(['playtime_minutes' => 100]);
    $action = app(RecordPlaytimeSnapshots::class);
    $today = CarbonImmutable::parse('2026-11-09', 'UTC');

    $action($user, $today);
    $entry->update(['playtime_minutes' => 150]);
    $written = $action($user, $today);

    expect($written)->toBe(1)
        ->and(PlaytimeSnapshot::count())->toBe(1)
        ->and(PlaytimeSnapshot::firstOrFail()->minutes_total)->toBe(150);
});

it('writes no snapshots for a private import', function () {
    $user = User::factory()->create();

    snapshotImport($user, 'owned_games_private');

    expect(PlaytimeSnapshot::count())->toBe(0);
});

it('reads the total as of a date', function () {
    $entry = LibraryEntry::factory()->create();
    PlaytimeSnapshot::factory()->for($entry, 'libraryEntry')->create(['captured_on' => '2026-11-01', 'minutes_total' => 100]);
    PlaytimeSnapshot::factory()->for($entry, 'libraryEntry')->create(['captured_on' => '2026-11-05', 'minutes_total' => 250]);

    expect(PlaytimeSnapshot::minutesAsOf($entry, CarbonImmutable::parse('2026-10-31')))->toBeNull()
        ->and(PlaytimeSnapshot::minutesAsOf($entry, CarbonImmutable::parse('2026-11-01')))->toBe(100)
        ->and(PlaytimeSnapshot::minutesAsOf($entry, CarbonImmutable::parse('2026-11-04')))->toBe(100)
        ->and(PlaytimeSnapshot::minutesAsOf($entry, CarbonImmutable::parse('2026-11-05')))->toBe(250)
        ->and(PlaytimeSnapshot::minutesAsOf($entry, CarbonImmutable::parse('2027-01-01')))->toBe(250);
});

it('uses a bounded number of queries for 200 entries', function () {
    $user = User::factory()->create();
    LibraryEntry::factory()->count(200)->for($user)->create();
    $action = app(RecordPlaytimeSnapshots::class);
    $date = CarbonImmutable::parse('2026-11-09', 'UTC');

    DB::enableQueryLog();
    $written = $action($user, $date);
    $queries = count(DB::getQueryLog());
    DB::flushQueryLog();

    expect($written)->toBe(200)
        ->and($queries)->toBeLessThanOrEqual(3);

    $action($user, $date);
    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(2);
});
