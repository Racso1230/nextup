<?php

use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\Tag;
use App\Models\User;

/**
 * A small library with real structure: 10 tagged, matched games, the first 5 played for 12 hours.
 */
function evalSeededUser(): User
{
    $user = User::factory()->create(['persona_name' => 'SecretPersonaName']);
    $tags = Tag::factory()->count(4)->create();

    foreach (range(1, 10) as $i) {
        $game = Game::factory()->matched()->create(['name' => "Seeded Game {$i}"]);
        $game->tags()->attach([$tags[$i % 4]->id, $tags[($i + 1) % 4]->id]);

        LibraryEntry::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'playtime_minutes' => $i <= 5 ? 720 : 0,
            'playtime_2weeks_minutes' => null,
            'last_played_at' => $i <= 5 ? now()->subDays(20 + $i) : null,
        ]);
    }

    return $user;
}

it('runs on a seeded library and prints both sections', function () {
    evalSeededUser();

    $this->artisan('eval:run')
        ->expectsOutputToContain('Leave-one-out')
        ->expectsOutputToContain('recommender')
        ->expectsOutputToContain('tag-overlap')
        ->expectsOutputToContain('Time-split backtest: needs 90 more days of snapshots')
        ->assertSuccessful();
});

it('prints the time-split table when there is enough history', function () {
    $user = evalSeededUser();

    foreach (LibraryEntry::where('user_id', $user->id)->get() as $entry) {
        PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-01-01', 'minutes_total' => 300]);
        PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-06-01', 'minutes_total' => 720]);
    }

    $this->artisan('eval:run')
        ->expectsOutputToContain('Time-split backtest (cut-off')
        ->expectsOutputToContain('most-hours')
        ->expectsOutputToContain('most-recent')
        ->assertSuccessful();
});

it('says how many more days of snapshots it needs', function () {
    $user = evalSeededUser();
    $entry = LibraryEntry::where('user_id', $user->id)->firstOrFail();
    PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-11-09', 'minutes_total' => 720]);
    PlaytimeSnapshot::factory()->create(['library_entry_id' => $entry->id, 'captured_on' => '2026-11-12', 'minutes_total' => 800]);

    $this->artisan('eval:run')
        ->expectsOutputToContain('Time-split backtest: needs 87 more days of snapshots (collection started 2026-11-09).')
        ->assertSuccessful();
});

it('fails for an unknown user', function () {
    $this->artisan('eval:run', ['--user' => 999])->expectsOutputToContain('No such user.')->assertFailed();
});

it('writes the results file without the Steam id or persona name', function () {
    $user = evalSeededUser();
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nextup-eval-'.uniqid().DIRECTORY_SEPARATOR.'results.md';

    $this->artisan('eval:run', ['--write' => true, '--path' => $path])->assertSuccessful();

    $md = (string) file_get_contents($path);
    unlink($path);
    rmdir(dirname($path));

    expect($md)->toStartWith('# Evaluation results')
        ->and($md)->toContain('Library size: 10 games')
        ->and($md)->toContain('Held-out games')
        ->and($md)->toContain('## Leave-one-out')
        ->and($md)->toContain('| ranker | trials |')
        ->and($md)->toContain('## Time-split backtest')
        ->and($md)->toContain('needs 90 more days')
        ->and($md)->toContain('method.md')
        ->and($md)->toContain('Seeded Game')
        ->and($md)->not->toContain($user->steam_id)
        ->and($md)->not->toContain('SecretPersonaName');
});

it('does not write a file without --write', function () {
    evalSeededUser();
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'nextup-eval-none-'.uniqid().'.md';

    $this->artisan('eval:run', ['--path' => $path])->assertSuccessful();

    expect(file_exists($path))->toBeFalse();
});
