<?php

use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('captured_on is a date and relationships resolve', function () {
    $snapshot = PlaytimeSnapshot::factory()->create(['captured_on' => '2026-11-01'])->fresh();

    expect($snapshot->captured_on->toDateString())->toBe('2026-11-01')
        ->and($snapshot->libraryEntry)->toBeInstanceOf(LibraryEntry::class)
        ->and($snapshot->libraryEntry->snapshots)->toHaveCount(1);
});

test('an entry has one snapshot per day', function () {
    $entry = LibraryEntry::factory()->create();
    PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-11-01']);

    expect(fn () => PlaytimeSnapshot::factory()->create([
        'library_entry_id' => $entry->id,
        'captured_on' => '2026-11-01',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('different entries can share a day', function () {
    PlaytimeSnapshot::factory()->count(2)->create(['captured_on' => '2026-11-01']);

    expect(PlaytimeSnapshot::query()->count())->toBe(2);
});

test('latestSnapshot picks the newest captured_on, not the newest insert', function () {
    $entry = LibraryEntry::factory()->create();
    PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-11-08', 'minutes_total' => 300]);
    PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-11-01', 'minutes_total' => 100]);

    expect($entry->latestSnapshot?->minutes_total)->toBe(300);
});

test('latestSnapshot is null with no snapshots', function () {
    expect(LibraryEntry::factory()->create()->latestSnapshot)->toBeNull();
});

test('deleting a user cascades through entries to snapshots', function () {
    $snapshot = PlaytimeSnapshot::factory()->create();

    $snapshot->libraryEntry->user->delete();

    expect(LibraryEntry::query()->count())->toBe(0)
        ->and(PlaytimeSnapshot::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});
