<?php

use App\Enums\LibraryStatus;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

test('status is unplayed with no playtime', function () {
    expect(LibraryEntry::factory()->unplayed()->make()->status)->toBe(LibraryStatus::Unplayed);
});

test('status is recent with playtime in the last two weeks', function () {
    expect(LibraryEntry::factory()->recent()->make()->status)->toBe(LibraryStatus::Recent);
});

test('status is played with playtime but none recent', function () {
    $entry = new LibraryEntry(['playtime_minutes' => 120, 'playtime_2weeks_minutes' => 0]);

    expect($entry->status)->toBe(LibraryStatus::Played);
});

test('a null 2-week value counts as not recent', function () {
    $entry = new LibraryEntry(['playtime_minutes' => 120, 'playtime_2weeks_minutes' => null]);

    expect($entry->status)->toBe(LibraryStatus::Played);
});

test('relationships resolve', function () {
    $entry = LibraryEntry::factory()->create();

    expect($entry->user)->toBeInstanceOf(User::class)
        ->and($entry->game)->toBeInstanceOf(Game::class)
        ->and($entry->user->libraryEntries)->toHaveCount(1)
        ->and($entry->game->libraryEntries)->toHaveCount(1);
});

test('a user has one entry per game', function () {
    $entry = LibraryEntry::factory()->create();

    expect(fn () => LibraryEntry::factory()->create([
        'user_id' => $entry->user_id,
        'game_id' => $entry->game_id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('deleting a user deletes their entries and keeps the games', function () {
    $entry = LibraryEntry::factory()->create();

    $entry->user->delete();

    expect(LibraryEntry::query()->count())->toBe(0)
        ->and(Game::query()->whereKey($entry->game_id)->exists())->toBeTrue();
});

test('a game with library entries cannot be deleted', function () {
    $entry = LibraryEntry::factory()->create();

    expect(fn () => $entry->game->delete())->toThrow(QueryException::class);
});

test('the withStatus scope matches the status accessor', function () {
    $user = User::factory()->create();

    foreach ([[0, null], [0, 0], [120, null], [120, 0], [120, 30], [5, 5]] as [$minutes, $twoWeeks]) {
        LibraryEntry::factory()->create([
            'user_id' => $user->id,
            'playtime_minutes' => $minutes,
            'playtime_2weeks_minutes' => $twoWeeks,
        ]);
    }

    foreach (LibraryStatus::cases() as $status) {
        $expected = LibraryEntry::all()
            ->filter(fn (LibraryEntry $entry) => $entry->status === $status)
            ->pluck('id')->sort()->values()->all();
        $actual = LibraryEntry::withStatus($status)->pluck('id')->sort()->values()->all();

        expect($expected)->not->toBeEmpty()
            ->and($actual)->toBe($expected);
    }
});
