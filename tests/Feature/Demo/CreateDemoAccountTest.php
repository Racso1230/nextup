<?php

use App\Demo\CreateDemoAccount;
use App\Demo\DemoLibrary;
use App\Demo\DemoPersona;
use App\Enums\ImportStatus;
use App\Enums\LibraryVisibility;
use App\Models\FriendActivity;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PlaytimeSnapshot;
use App\Models\Tag;
use App\Recommendations\RunRecommendations;
use App\Recommender\Recommender;
use App\Recommender\SessionContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

it('creates a demo user with the persona library', function () {
    $user = app(CreateDemoAccount::class)();

    expect($user->is_demo)->toBeTrue()
        ->and($user->fresh()->is_demo)->toBeTrue()
        ->and($user->persona_name)->toBe('Demo player')
        ->and($user->avatar_url)->toBeNull()
        ->and($user->library_visibility)->toBe(LibraryVisibility::Public)
        ->and($user->import_status)->toBe(ImportStatus::Done)
        ->and($user->imported_at)->not->toBeNull();

    $count = count(DemoLibrary::catalogue());

    expect(LibraryEntry::where('user_id', $user->id)->count())->toBe($count)
        ->and(PlaytimeSnapshot::count())->toBe($count)
        ->and(Game::count())->toBe($count)
        ->and(Tag::count())->toBeGreaterThan(50)
        ->and(DB::table('game_tag')->count())->toBeGreaterThan($count)
        ->and(FriendActivity::where('user_id', $user->id)->count())->toBe(count(DemoPersona::friendActivity()));

    // Fourteen loved games, the rest mostly unplayed.
    expect(LibraryEntry::where('user_id', $user->id)->where('playtime_minutes', '>=', 20 * 60)->whereNull('playtime_2weeks_minutes')->count())->toBe(14)
        ->and(LibraryEntry::where('user_id', $user->id)->where('playtime_2weeks_minutes', '>', 0)->count())->toBe(3)
        ->and(LibraryEntry::where('user_id', $user->id)->where('playtime_minutes', 0)->count())->toBeGreaterThan(90);
});

it('gives each snapshot the entry playtime', function () {
    $user = app(CreateDemoAccount::class)();

    $mismatched = DB::table('playtime_snapshots as s')
        ->join('library_entries as e', 'e.id', '=', 's.library_entry_id')
        ->where('e.user_id', $user->id)
        ->whereColumn('s.minutes_total', '!=', 'e.playtime_minutes')
        ->count();

    expect($mismatched)->toBe(0);
});

it('uses a steam id that can never be a real SteamID64', function () {
    $user = app(CreateDemoAccount::class)();

    expect($user->steam_id)->toStartWith(CreateDemoAccount::STEAM_ID_PREFIX)
        ->and($user->steam_id)->toMatch('/^\d{17}$/')
        // Account 0 is 76561197960265728. Everything below it is outside the real space.
        ->and((int) $user->steam_id)->toBeLessThan(76561197960265728);
});

it('gives every demo account its own steam id', function () {
    $a = app(CreateDemoAccount::class)();
    $b = app(CreateDemoAccount::class)();

    expect($a->steam_id)->not->toBe($b->steam_id);
});

it('uses a bounded number of queries', function () {
    DB::enableQueryLog();

    app(CreateDemoAccount::class)();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Bulk inserts and upserts: the count does not grow with the 127 games or thousands of tag links.
    expect($queries)->toBeLessThan(30);
});

it('is fast', function () {
    $start = microtime(true);

    app(CreateDemoAccount::class)();

    expect(microtime(true) - $start)->toBeLessThan(1.0);
});

it('shares the catalogue between accounts instead of duplicating it', function () {
    app(CreateDemoAccount::class)();
    $games = Game::count();
    $tags = Tag::count();
    $links = DB::table('game_tag')->count();

    app(CreateDemoAccount::class)();

    expect(Game::count())->toBe($games)
        ->and(Tag::count())->toBe($tags)
        ->and(DB::table('game_tag')->count())->toBe($links)
        ->and(LibraryEntry::count())->toBe($games * 2);
});

it('reuses a game a real user already has', function () {
    $existing = Game::factory()->create(['steam_appid' => 489830, 'name' => 'Skyrim old name']);

    app(CreateDemoAccount::class)();

    expect(Game::where('steam_appid', 489830)->count())->toBe(1)
        ->and(Game::where('steam_appid', 489830)->value('id'))->toBe($existing->id)
        ->and(Game::where('steam_appid', 489830)->value('igdb_id'))->not->toBeNull();
});

it('never calls Steam or IGDB', function () {
    Http::fake();

    app(CreateDemoAccount::class)();

    Http::assertNothingSent();
});

it('ranks the seeded account the same as the hero library', function () {
    $now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');
    $this->travelTo($now);

    $user = app(CreateDemoAccount::class)($now);
    $run = app(RunRecommendations::class)($user, SessionContext::default());

    expect($run)->not->toBeNull();

    $fromDb = $run->picks->take(10)->load('libraryEntry.game')
        ->map(fn ($pick): string => $pick->libraryEntry->game->name)
        ->all();

    $fromFixture = array_map(
        fn ($pick): string => $pick->game->name,
        array_slice((new Recommender)->rank(DemoLibrary::context($now)), 0, 10),
    );

    expect($fromDb)->toBe($fromFixture);
});
