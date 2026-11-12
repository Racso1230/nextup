<?php

use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\RecommendationPick;
use App\Models\RecommendationRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A fresh stored run with $count picks, so the page never has to rank.
 */
function storedRun(User $user, int $count = 12): RecommendationRun
{
    $user->forceFill(['imported_at' => now()->subHour(), 'import_status' => ImportStatus::Done])->save();
    $run = RecommendationRun::factory()->create(['user_id' => $user->id]);

    for ($rank = 1; $rank <= $count; $rank++) {
        $entry = LibraryEntry::factory()->create([
            'user_id' => $user->id,
            'game_id' => Game::factory()->create(['name' => "Game {$rank}", 'cover_image_id' => "co{$rank}"])->id,
        ]);

        RecommendationPick::factory()->create([
            'recommendation_run_id' => $run->id,
            'library_entry_id' => $entry->id,
            'rank' => $rank,
            'score' => 0.8 - $rank / 100,
            'contributions' => [
                'taste' => ['value' => 0.82, 'weight' => 0.35, 'weighted' => 0.287],
                'momentum' => ['value' => null, 'weight' => 0.2, 'weighted' => 0.0],
                'fit' => ['value' => 0.5, 'weight' => 0.2, 'weighted' => 0.1],
                'freshness' => ['value' => 1.0, 'weight' => 0.1, 'weighted' => 0.1],
                'mismatch' => ['value' => 0.3, 'weight' => 0.1, 'weighted' => -0.03],
            ],
        ]);
    }

    return $run;
}

test('guests are redirected to the login page', function () {
    $this->get(route('play-next'))->assertRedirect(route('login'));
});

test('it renders the top ten picks in rank order', function () {
    $user = User::factory()->create();
    $run = storedRun($user);

    $this->actingAs($user)->get(route('play-next'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PlayNext')
            ->where('state', 'ready')
            ->has('picks', 10)
            ->where('picks.0.rank', 1)
            ->where('picks.0.name', 'Game 1')
            ->where('picks.9.rank', 10)
            ->where('ranked_at', $run->created_at?->toIso8601String())
            ->where('privacy', null)
            ->where('import.status', 'done'));
});

test('only the top pick carries signals, without null values or the mismatch penalty', function () {
    $user = User::factory()->create();
    storedRun($user);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picks.0.signals', [
                ['key' => 'taste', 'label' => 'Taste match', 'value' => 0.82],
                ['key' => 'fit', 'label' => 'Fits tonight', 'value' => 0.5],
                ['key' => 'freshness', 'label' => 'Fresh start', 'value' => 1],
            ])
            ->missing('picks.1.signals')
            ->missing('picks.9.signals'));
});

test('a friends bar appears when friends played the game, and not at zero', function () {
    $user = User::factory()->create();
    $run = storedRun($user, 1);
    $pick = $run->picks()->firstOrFail();
    $pick->update(['contributions' => array_merge($pick->contributions, [
        'social' => ['value' => 2 / 3, 'weight' => 0.1, 'weighted' => 0.0667],
    ])]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picks.0.signals.2', ['key' => 'social', 'label' => 'Friends', 'value' => 2 / 3]));

    $pick->update(['contributions' => array_merge($pick->contributions, [
        'social' => ['value' => 0.0, 'weight' => 0.1, 'weighted' => 0.0],
    ])]);

    $keys = collect($this->actingAs($user)->get(route('play-next'))->inertiaProps('picks.0.signals'))->pluck('key');

    expect($keys)->not->toContain('social');
});

test('a zero freshness bar is hidden, because it would read as a claim', function () {
    $user = User::factory()->create();
    $run = storedRun($user, 1);
    $pick = $run->picks()->firstOrFail();
    $pick->update(['contributions' => array_merge($pick->contributions, [
        'freshness' => ['value' => 0.0, 'weight' => 0.1, 'weighted' => 0.0],
    ])]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picks.0.signals', [
                ['key' => 'taste', 'label' => 'Taste match', 'value' => 0.82],
                ['key' => 'fit', 'label' => 'Fits tonight', 'value' => 0.5],
            ]));
});

test('a zero momentum bar is hidden, and shown once it is above zero', function () {
    $user = User::factory()->create();
    $run = storedRun($user, 1);
    $pick = $run->picks()->firstOrFail();
    $pick->update(['contributions' => array_merge($pick->contributions, [
        'momentum' => ['value' => 0.0, 'weight' => 0.2, 'weighted' => 0.0],
    ])]);

    $keys = collect($this->actingAs($user)->get(route('play-next'))->inertiaProps('picks.0.signals'))->pluck('key');

    expect($keys)->not->toContain('momentum');

    $pick->update(['contributions' => array_merge($pick->contributions, [
        'momentum' => ['value' => 0.4, 'weight' => 0.2, 'weighted' => 0.08],
    ])]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picks.0.signals.1', ['key' => 'momentum', 'label' => 'Momentum', 'value' => 0.4]));
});

test('scores are whole numbers from 0 to 100', function () {
    $user = User::factory()->create();
    storedRun($user);

    /** @var list<array{score: mixed}> $picks */
    $picks = $this->actingAs($user)->get(route('play-next'))->inertiaProps('picks');

    foreach ($picks as $pick) {
        expect($pick['score'])->toBeInt()->toBeBetween(0, 100);
    }

    expect($picks[0]['score'])->toBe(79);
});

test('the top pick uses the big cover and the rest the small one', function () {
    $user = User::factory()->create();
    storedRun($user);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picks.0.cover_url', 'https://images.igdb.com/igdb/image/upload/t_cover_big/co1.jpg')
            ->where('picks.1.cover_url', 'https://images.igdb.com/igdb/image/upload/t_cover_small/co2.jpg'));
});

test('it uses a bounded number of queries', function () {
    $user = User::factory()->create();
    storedRun($user);

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('play-next'))->assertOk();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(12);
});

test('it shows the importing state while an import is queued or running', function (ImportStatus $status) {
    $user = User::factory()->create(['import_status' => $status]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'importing')
            ->where('picks', [])
            ->where('ranked_at', null));
})->with([ImportStatus::Queued, ImportStatus::Running]);

test('it shows the private state with privacy steps', function () {
    $user = User::factory()->create([
        'import_status' => ImportStatus::Private,
        'library_visibility' => LibraryVisibility::Private,
    ]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'private')
            ->has('privacy.settings_url')
            ->has('privacy.steps'));
});

test('it shows the empty state when there are no library entries', function () {
    $user = User::factory()->create(['import_status' => ImportStatus::Done]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'empty')->where('privacy', null));
});

test('it shows the nothing state when entries exist but none can be suggested', function () {
    $user = User::factory()->create(['import_status' => ImportStatus::Done, 'imported_at' => now()]);
    // An unmatched game has no details, so it is not a candidate.
    LibraryEntry::factory()->create(['user_id' => $user->id, 'game_id' => Game::factory()->create()->id]);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'nothing')->where('picks', []));
});

test('it passes the session context to the page as filters', function () {
    $user = User::factory()->create();
    storedRun($user);

    $this->actingAs($user)->get(route('play-next'))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => null, 'mood' => null, 'with' => null]));

    $this->actingAs($user)->get(route('play-next', ['hours' => 1, 'mood' => 'chill', 'with' => 'friends']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => 1, 'mood' => 'chill', 'with' => 'friends']));
});
