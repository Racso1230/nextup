<?php

use App\Demo\DemoLibrary;
use App\Demo\DemoPersona;
use App\Recommender\Explainer;
use App\Recommender\Recommender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Boots the app (for config and paths) but not the database: there is no RefreshDatabase here, so a
// query would fail on the empty in-memory sqlite.
uses(TestCase::class);

it('loads and caches the catalogue', function () {
    $catalogue = DemoLibrary::catalogue();

    expect($catalogue)->toHaveCount(127)
        ->and(DemoLibrary::catalogue())->toBe($catalogue)
        ->and($catalogue[0])->toHaveKeys(['steam_appid', 'name', 'igdb_id', 'cover_image_id', 'tags']);
});

it('builds a context and ranks it without touching the database', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');
    $context = DemoLibrary::context($now);
    $ranked = (new Recommender)->rank($context);

    expect($queries)->toBe(0)
        ->and($context->library)->toHaveCount(127)
        ->and($context->library[0]->entryId)->toBe(1)
        ->and($context->library[126]->entryId)->toBe(127)
        ->and($ranked)->not->toBeEmpty();
});

it('puts an unplayed or recent game on top, never a finished one', function () {
    $now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');
    $catalogue = DemoLibrary::catalogue();
    $ranked = (new Recommender)->rank(DemoLibrary::context($now));

    foreach (array_slice($ranked, 0, 5) as $pick) {
        $kind = DemoPersona::kind($catalogue[$pick->game->entryId - 1]['steam_appid']);

        // Loved games are all past their time to beat and idle, so they never reach the picks.
        expect($kind)->not->toBe('loved');
    }

    $topKind = DemoPersona::kind($catalogue[$ranked[0]->game->entryId - 1]['steam_appid']);

    expect($topKind)->toBeIn(['unplayed', 'recent'])
        ->and((new Explainer)->explain($ranked[0], $now->toDateTimeImmutable()))->not->toBe('');
});

it('ranks identically every time', function () {
    $now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');

    $names = fn (): array => array_map(
        fn ($pick): string => $pick->game->name,
        (new Recommender)->rank(DemoLibrary::context($now)),
    );

    expect($names())->toBe($names());
});
