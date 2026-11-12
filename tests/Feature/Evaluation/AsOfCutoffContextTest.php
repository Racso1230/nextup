<?php

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\Tag;
use App\Models\User;
use App\Recommendations\AsOfCutoffContext;
use App\Recommender\GameFacts;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, string|int>  $snapshots  date => lifetime minutes
 * @param  int  $current  the entry's playtime today
 */
function asOfEntry(User $user, int $current, array $snapshots, ?Game $game = null): LibraryEntry
{
    $entry = LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => ($game ?? Game::factory()->matched()->create())->id,
        'playtime_minutes' => $current,
    ]);

    foreach ($snapshots as $date => $minutes) {
        PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => $date, 'minutes_total' => $minutes]);
    }

    return $entry;
}

/** @param  list<GameFacts>  $library */
function asOfFacts(array $library, LibraryEntry $entry): ?GameFacts
{
    foreach ($library as $facts) {
        if ($facts->entryId === $entry->id) {
            return $facts;
        }
    }

    return null;
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cutoff = CarbonImmutable::parse('2026-11-01', 'UTC');
    $this->asOf = new AsOfCutoffContext;
});

it('returns null when the history starts after the cut-off, or does not exist', function () {
    expect($this->asOf->build($this->user, $this->cutoff))->toBeNull();

    asOfEntry($this->user, 100, ['2026-11-05' => 100]);

    expect($this->asOf->build($this->user, $this->cutoff))->toBeNull();
});

it('leaves out games first seen after the cut-off', function () {
    $early = asOfEntry($this->user, 100, ['2026-10-10' => 100]);
    $late = asOfEntry($this->user, 30, ['2026-11-05' => 30]);
    asOfEntry($this->user, 10, []); // never snapshotted

    $built = $this->asOf->build($this->user, $this->cutoff);

    expect($built['context']->library)->toHaveCount(1)
        ->and(asOfFacts($built['context']->library, $early))->not->toBeNull()
        ->and(asOfFacts($built['context']->library, $late))->toBeNull()
        ->and($built['context']->now->format('Y-m-d'))->toBe('2026-11-01');
});

it('uses the latest snapshot on or before the cut-off for playtime', function () {
    $entry = asOfEntry($this->user, 400, ['2026-10-10' => 100, '2026-10-20' => 200, '2026-11-01' => 260, '2026-11-05' => 400]);

    $facts = asOfFacts($this->asOf->build($this->user, $this->cutoff)['context']->library, $entry);

    expect($facts->playtimeMinutes)->toBe(260);
});

it('dates last played from the last snapshot where minutes grew', function () {
    $grew = asOfEntry($this->user, 400, ['2026-10-10' => 100, '2026-10-20' => 200, '2026-10-28' => 260, '2026-11-05' => 400]);
    $flat = asOfEntry($this->user, 50, ['2026-10-10' => 50, '2026-10-25' => 50]);

    $library = $this->asOf->build($this->user, $this->cutoff)['context']->library;

    expect(asOfFacts($library, $grew)->lastPlayedAt->format('Y-m-d'))->toBe('2026-10-28')
        ->and(asOfFacts($library, $flat)->lastPlayedAt)->toBeNull();
});

it('measures the 14-day growth before the cut-off from snapshots', function () {
    // Window starts 2026-10-18. Baselines: 100 (10-10), 50 (10-10); the third game appears inside the window.
    $grew = asOfEntry($this->user, 400, ['2026-10-10' => 100, '2026-10-20' => 200, '2026-10-28' => 260]);
    $flat = asOfEntry($this->user, 50, ['2026-10-10' => 50]);
    $new = asOfEntry($this->user, 40, ['2026-10-25' => 40]);

    $library = $this->asOf->build($this->user, $this->cutoff)['context']->library;

    expect(asOfFacts($library, $grew)->recentMinutesFromSnapshots)->toBe(160)
        ->and(asOfFacts($library, $grew)->playtime2WeeksMinutes)->toBe(160)
        ->and(asOfFacts($library, $flat)->recentMinutesFromSnapshots)->toBe(0)
        ->and(asOfFacts($library, $new)->recentMinutesFromSnapshots)->toBeNull();
});

it('has no 14-day growth when the history does not cover the window', function () {
    $entry = asOfEntry($this->user, 100, ['2026-10-25' => 80, '2026-10-30' => 100]);

    $facts = asOfFacts($this->asOf->build($this->user, $this->cutoff)['context']->library, $entry);

    expect($facts->recentMinutesFromSnapshots)->toBeNull()
        ->and($facts->playtime2WeeksMinutes)->toBeNull();
});

it('marks games whose playtime grew after the cut-off as relevant', function () {
    $grew = asOfEntry($this->user, 400, ['2026-10-10' => 100, '2026-11-05' => 400]);
    asOfEntry($this->user, 50, ['2026-10-10' => 50]);
    asOfEntry($this->user, 30, ['2026-11-05' => 30]); // not in the library yet

    expect($this->asOf->build($this->user, $this->cutoff)['relevant'])->toBe([$grew->id]);
});

it('keeps tags and time to beat from the current metadata', function () {
    $game = Game::factory()->matched()->create(['ttb_normally' => 36_000]);
    $game->tags()->attach(Tag::factory()->create());
    $entry = asOfEntry($this->user, 100, ['2026-10-10' => 100], $game);

    $facts = asOfFacts($this->asOf->build($this->user, $this->cutoff)['context']->library, $entry);

    expect($facts->tags)->toHaveCount(1)
        ->and($facts->ttbNormallySeconds)->toBe(36_000)
        ->and($facts->igdbId)->toBe($game->igdb_id);
});

it('reports how many more days of snapshots a 90-day split needs', function () {
    expect($this->asOf->daysUntilAvailable($this->user))->toBe(90)
        ->and($this->asOf->cutoffFor($this->user))->toBeNull()
        ->and($this->asOf->firstSnapshotOn($this->user))->toBeNull();

    asOfEntry($this->user, 100, ['2026-11-09' => 100, '2026-11-12' => 120]);

    expect($this->asOf->daysUntilAvailable($this->user))->toBe(87)
        ->and($this->asOf->firstSnapshotOn($this->user)->toDateString())->toBe('2026-11-09');

    asOfEntry($this->user, 100, ['2027-02-07' => 100]);

    expect($this->asOf->daysUntilAvailable($this->user))->toBe(0)
        ->and($this->asOf->cutoffFor($this->user)->toDateString())->toBe('2026-11-09');
});

it('ignores other users snapshots', function () {
    asOfEntry(User::factory()->create(), 100, ['2026-01-01' => 100, '2026-11-12' => 200]);

    expect($this->asOf->daysUntilAvailable($this->user))->toBe(90)
        ->and($this->asOf->build($this->user, $this->cutoff))->toBeNull();
});
