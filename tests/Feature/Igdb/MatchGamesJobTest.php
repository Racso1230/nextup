<?php

use App\Enums\MatchStatus;
use App\Igdb\IgdbApiException;
use App\Jobs\EnrichGames;
use App\Jobs\MatchGames;
use App\Models\Game;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\IgdbFixtures;

function configureIgdb(): void
{
    config(['services.igdb.client_id' => 'test-id', 'services.igdb.client_secret' => 'test-secret']);
}

function fakeIgdbMatching(mixed $external = null): void
{
    Http::fake([
        'id.twitch.tv/*' => Http::response(IgdbFixtures::load('token')),
        'api.igdb.com/v4/external_games' => $external ?? Http::response(IgdbFixtures::load('external_games_steam')),
    ]);
}

function runMatch(?MatchGames $job = null): MatchGames
{
    $job ??= new MatchGames;
    app()->call([$job, 'handle']);

    return $job;
}

beforeEach(function () {
    configureIgdb();
    Queue::fake();
    $this->freezeSecond();
});

it('matches found appids and schedules missing ones for a retry in a day', function () {
    $found = Game::factory()->create(['steam_appid' => 620]);
    $missing = Game::factory()->create(['steam_appid' => 99999999]);
    fakeIgdbMatching();

    runMatch();

    $found->refresh();
    expect($found->match_status)->toBe(MatchStatus::Matched)
        ->and($found->igdb_id)->toBe(72)
        ->and($found->match_attempts)->toBe(1)
        ->and($found->next_match_at)->toBeNull();

    $missing->refresh();
    expect($missing->match_status)->toBe(MatchStatus::Unmatched)
        ->and($missing->igdb_id)->toBeNull()
        ->and($missing->match_attempts)->toBe(1)
        ->and($missing->next_match_at?->equalTo(now()->addDay()))->toBeTrue();

    Queue::assertPushed(EnrichGames::class);
});

it('matches two appids that map to the same igdb game', function () {
    $a = Game::factory()->create(['steam_appid' => 620]);
    $b = Game::factory()->create(['steam_appid' => 621]);
    fakeIgdbMatching(Http::response([
        ['id' => 1, 'game' => 72, 'uid' => '620'],
        ['id' => 2, 'game' => 72, 'uid' => '621'],
    ]));

    runMatch();

    expect($a->refresh()->igdb_id)->toBe(72)
        ->and($b->refresh()->igdb_id)->toBe(72)
        ->and(Game::where('match_status', MatchStatus::Matched)->count())->toBe(2);
});

it('retries due unmatched games and skips the ones that are not due', function () {
    $due = Game::factory()->create(['steam_appid' => 620, 'match_status' => MatchStatus::Unmatched,
        'match_attempts' => 2, 'next_match_at' => now()->subMinute()]);
    $later = Game::factory()->create(['steam_appid' => 413150, 'match_status' => MatchStatus::Unmatched,
        'match_attempts' => 2, 'next_match_at' => now()->addHour()]);
    $done = Game::factory()->matched()->create(['steam_appid' => 1145360]);
    fakeIgdbMatching();

    runMatch();

    expect($due->refresh()->match_status)->toBe(MatchStatus::Matched)
        ->and($due->match_attempts)->toBe(3)
        ->and($later->refresh()->match_status)->toBe(MatchStatus::Unmatched)
        ->and($later->match_attempts)->toBe(2)
        ->and($done->refresh()->match_attempts)->toBe(1);

    Http::assertSent(fn ($request) => ! str_starts_with($request->url(), 'https://api.igdb.com')
        || (str_contains($request->body(), '"620"')
            && ! str_contains($request->body(), '413150')
            && ! str_contains($request->body(), '1145360')));
});

it('applies the backoff sequence as attempts accumulate, capped at 30 days', function () {
    $game = Game::factory()->create(['steam_appid' => 99999999]);
    fakeIgdbMatching(Http::response([]));

    foreach ([1, 3, 7, 14, 30, 30] as $days) {
        runMatch();
        $game->refresh();
        expect($game->next_match_at?->equalTo(now()->addDays($days)))->toBeTrue();

        $game->update(['next_match_at' => now()->subSecond()]);
    }

    expect($game->match_attempts)->toBe(6);
});

it('releases with the retry-after delay on a 429 and restores statuses', function () {
    $pending = Game::factory()->create(['steam_appid' => 620]);
    $unmatched = Game::factory()->create(['steam_appid' => 621, 'match_status' => MatchStatus::Unmatched,
        'match_attempts' => 1, 'next_match_at' => now()->subMinute()]);
    fakeIgdbMatching(Http::response('', 429, ['Retry-After' => '45']));

    $job = runMatch((new MatchGames)->withFakeQueueInteractions());

    $job->assertReleased(delay: 45);
    expect($pending->refresh()->match_status)->toBe(MatchStatus::Pending)
        ->and($unmatched->refresh()->match_status)->toBe(MatchStatus::Unmatched)
        ->and($unmatched->match_attempts)->toBe(1);
    Queue::assertNotPushed(EnrichGames::class);
});

it('releases for 30 seconds on a 429 without Retry-After', function () {
    Game::factory()->create(['steam_appid' => 620]);
    fakeIgdbMatching(Http::response('', 429));

    runMatch((new MatchGames)->withFakeQueueInteractions())->assertReleased(delay: 30);
});

it('rethrows other api errors and puts the games back', function () {
    $game = Game::factory()->create(['steam_appid' => 620]);
    fakeIgdbMatching(Http::response('', 500));

    expect(fn () => runMatch())->toThrow(IgdbApiException::class);
    expect($game->refresh()->match_status)->toBe(MatchStatus::Pending);
});

it('leaves nothing retrying after failed()', function () {
    $stuck = Game::factory()->create(['match_status' => MatchStatus::Retrying]);
    $other = Game::factory()->matched()->create();

    (new MatchGames)->failed(new RuntimeException('boom'));

    $stuck->refresh();
    expect($stuck->match_status)->toBe(MatchStatus::Unmatched)
        ->and($stuck->next_match_at?->equalTo(now()->addHour()))->toBeTrue()
        ->and($other->refresh()->match_status)->toBe(MatchStatus::Matched)
        ->and(Game::where('match_status', MatchStatus::Retrying)->exists())->toBeFalse();
});

it('does nothing when igdb is not configured', function () {
    config(['services.igdb.client_id' => '', 'services.igdb.client_secret' => '']);
    $game = Game::factory()->create(['steam_appid' => 620]);
    Http::fake();

    runMatch();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
    expect($game->refresh()->match_status)->toBe(MatchStatus::Pending)
        ->and($game->match_attempts)->toBe(0);
});

it('is globally unique and rate limited', function () {
    $job = new MatchGames;

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class)
        ->and($job->middleware()[1])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120, 600]);
});
