<?php

use App\Enums\MatchStatus;
use App\Enums\TagType;
use App\Jobs\EnrichGames;
use App\Models\Game;
use App\Models\Tag;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\IgdbFixtures;

function fakeIgdbEnrichment(mixed $games = null, mixed $times = null): void
{
    Http::fake([
        'id.twitch.tv/*' => Http::response(IgdbFixtures::load('token')),
        'api.igdb.com/v4/games' => $games ?? Http::response(IgdbFixtures::load('games')),
        'api.igdb.com/v4/game_time_to_beats' => $times ?? Http::response(IgdbFixtures::load('time_to_beats')),
    ]);
}

function runEnrich(?EnrichGames $job = null): void
{
    app()->call([$job ?? new EnrichGames, 'handle']);
}

beforeEach(function () {
    config(['services.igdb.client_id' => 'test-id', 'services.igdb.client_secret' => 'test-secret']);
    $this->freezeSecond();
});

it('writes metadata, tags and the pivot', function () {
    $game = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null, 'name' => 'Portal 2 (Steam)']);
    fakeIgdbEnrichment();

    runEnrich();

    $game->refresh();
    expect($game->slug)->toBe('portal-2')
        ->and($game->first_release_date?->toDateString())->toBe('2011-04-19')
        ->and($game->cover_image_id)->toBe('co1rs4')
        ->and($game->similar_igdb_ids)->toBe([71, 1030, 7346])
        ->and($game->ttb_hastily)->toBe(29700)
        ->and($game->ttb_normally)->toBe(36000)
        ->and($game->ttb_completely)->toBe(90000)
        ->and($game->enriched_at?->equalTo(now()))->toBeTrue()
        ->and($game->name)->toBe('Portal 2 (Steam)')
        ->and($game->tags()->count())->toBe(11);

    $genre = Tag::where('type', TagType::Genre)->where('igdb_id', 5)->firstOrFail();
    expect($genre->name)->toBe('Shooter')
        ->and($game->tags->contains($genre))->toBeTrue();
    // Same igdb id in two tag types stays two tags.
    expect(Tag::where('igdb_id', 1)->count())->toBeGreaterThan(1);
});

it('replaces the tag set exactly on re-enrichment and updates names', function () {
    $game = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    $stale = Tag::factory()->genre()->create(['igdb_id' => 99999, 'name' => 'Removed']);
    $renamed = Tag::factory()->genre()->create(['igdb_id' => 5, 'name' => 'Old name']);
    $game->tags()->attach([$stale->id, $renamed->id]);
    fakeIgdbEnrichment();

    runEnrich();

    expect($game->tags()->pluck('tags.id')->all())->not->toContain($stale->id)
        ->and($game->tags()->pluck('tags.id')->all())->toContain($renamed->id)
        ->and($game->tags()->count())->toBe(11)
        ->and($renamed->refresh()->name)->toBe('Shooter');
});

it('stores unknown time to beat as null, never zero', function () {
    $partial = Game::factory()->matched()->create(['igdb_id' => 113112, 'enriched_at' => null]);
    $none = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null,
        'ttb_hastily' => 5, 'ttb_normally' => 5, 'ttb_completely' => 5]);
    fakeIgdbEnrichment(times: Http::response([
        ['id' => 1, 'game_id' => 113112, 'hastily' => 0, 'normally' => 79200],
        ['id' => 2, 'game_id' => 72, 'hastily' => 0, 'normally' => 0, 'completely' => 0],
    ]));

    runEnrich();

    $partial->refresh();
    expect($partial->ttb_hastily)->toBeNull()
        ->and($partial->ttb_normally)->toBe(79200)
        ->and($partial->ttb_completely)->toBeNull();
    $none->refresh();
    expect($none->ttb_hastily)->toBeNull()
        ->and($none->ttb_normally)->toBeNull()
        ->and($none->ttb_completely)->toBeNull();
});

it('enriches every row sharing an igdb id', function () {
    $a = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    $b = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    fakeIgdbEnrichment();

    runEnrich();

    expect($a->refresh()->slug)->toBe('portal-2')
        ->and($b->refresh()->slug)->toBe('portal-2')
        ->and($a->tags()->count())->toBe(11)
        ->and($b->tags()->count())->toBe(11);
    Http::assertSentCount(3); // token, games, time to beat: one lookup for the shared id
});

it('skips fresh games and enriches stale ones', function () {
    $fresh = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => now()->subDays(29), 'slug' => 'keep']);
    $stale = Game::factory()->matched()->create(['igdb_id' => 113112, 'enriched_at' => now()->subDays(31)]);
    fakeIgdbEnrichment();

    runEnrich();

    expect($fresh->refresh()->slug)->toBe('keep')
        ->and($stale->refresh()->slug)->toBe('hades--1');
});

it('sends games back to unmatched when igdb no longer returns the id', function () {
    $gone = Game::factory()->matched()->create(['igdb_id' => 424242, 'enriched_at' => null]);
    $kept = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    fakeIgdbEnrichment();

    runEnrich();

    $gone->refresh();
    expect($gone->igdb_id)->toBeNull()
        ->and($gone->match_status)->toBe(MatchStatus::Unmatched)
        ->and($gone->next_match_at?->equalTo(now()->addDay()))->toBeTrue()
        ->and($kept->refresh()->igdb_id)->toBe(72);
});

it('trims and caps tag names from igdb', function () {
    Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    fakeIgdbEnrichment(Http::response([[
        'id' => 72, 'name' => 'Portal 2', 'slug' => 'portal-2',
        'genres' => [['id' => 5, 'name' => '  '.str_repeat('é', 300).'  '], ['id' => 6, 'name' => '   ']],
        'keywords' => [['id' => 7, 'name' => '<script>alert(1)</script>']],
    ]]));

    runEnrich();

    expect(Tag::where('igdb_id', 5)->firstOrFail()->name)->toBe(str_repeat('é', 255))
        ->and(Tag::where('igdb_id', 6)->exists())->toBeFalse()
        ->and(Tag::where('igdb_id', 7)->firstOrFail()->name)->toBe('<script>alert(1)</script>');
});

it('releases on a 429 with the retry-after delay', function () {
    $game = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    fakeIgdbEnrichment(Http::response('', 429, ['Retry-After' => '12']));

    $job = (new EnrichGames)->withFakeQueueInteractions();
    runEnrich($job);

    $job->assertReleased(delay: 12);
    expect($game->refresh()->enriched_at)->toBeNull();
});

it('does nothing when igdb is not configured', function () {
    config(['services.igdb.client_id' => '', 'services.igdb.client_secret' => '']);
    $game = Game::factory()->matched()->create(['igdb_id' => 72, 'enriched_at' => null]);
    Http::fake();

    runEnrich();

    Http::assertNothingSent();
    expect($game->refresh()->enriched_at)->toBeNull();
});

it('is globally unique and rate limited', function () {
    $job = new EnrichGames;

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($job->middleware()[1])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120, 600]);
});

it('enriches 500 games in a fixed, small number of queries', function () {
    $rows = [];
    $igdb = [];
    for ($i = 1; $i <= 500; $i++) {
        $rows[] = ['steam_appid' => $i, 'name' => "Game {$i}", 'igdb_id' => $i, 'match_status' => 'matched',
            'match_attempts' => 1, 'created_at' => now(), 'updated_at' => now()];
        $igdb[] = ['id' => $i, 'name' => "Game {$i}", 'slug' => "game-{$i}",
            'genres' => [['id' => $i % 20 + 1, 'name' => 'Genre '.($i % 20)]],
            'themes' => [['id' => $i % 7 + 1, 'name' => 'Theme '.($i % 7)]]];
    }
    Game::insert($rows);
    fakeIgdbEnrichment(Http::response($igdb), Http::response([]));

    DB::enableQueryLog();
    runEnrich();
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Selection (2 passes), lookup, tag upsert and read-back, one game upsert (2 chunks), pivot delete and
    // insert, plus transaction statements. The point is that it does not grow with the 500 games.
    expect($queries)->toBeLessThan(30)
        ->and(Game::whereNotNull('enriched_at')->count())->toBe(500)
        ->and(DB::table('game_tag')->count())->toBe(1000);
});
