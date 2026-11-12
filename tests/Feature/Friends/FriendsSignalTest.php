<?php

use App\Models\FriendActivity;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use App\Recommendations\LibraryContextFactory;
use App\Recommendations\RunRecommendations;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC');
    $this->travelTo($this->now);
});

function friendEntry(User $user, int $appId): LibraryEntry
{
    return LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->matched()->create(['steam_appid' => $appId, 'ttb_normally' => 1_000_000])->id,
        'playtime_minutes' => 0,
        'playtime_2weeks_minutes' => null,
        'last_played_at' => null,
    ]);
}

function activity(User $user, int $appId, int $count): void
{
    FriendActivity::create(['user_id' => $user->id, 'steam_appid' => $appId, 'friends_played' => $count, 'captured_on' => '2026-11-12']);
}

it('leaves friends played null for every entry when there is no friend activity', function () {
    $user = User::factory()->create();
    friendEntry($user, 440);
    friendEntry($user, 570);
    // Another user's activity must not leak in.
    activity(User::factory()->create(), 440, 3);

    $library = app(LibraryContextFactory::class)->build($user, $this->now)->library;

    expect(array_map(fn ($facts) => $facts->friendsPlayedRecently, $library))->toBe([null, null]);
});

it('gives counts, and zero for games no friend played, when there is friend activity', function () {
    $user = User::factory()->create();
    $played = friendEntry($user, 440);
    $unplayed = friendEntry($user, 570);
    activity($user, 440, 2);

    $byEntry = [];

    foreach (app(LibraryContextFactory::class)->build($user, $this->now)->library as $facts) {
        $byEntry[$facts->entryId] = $facts->friendsPlayedRecently;
    }

    expect($byEntry[$played->id])->toBe(2)->and($byEntry[$unplayed->id])->toBe(0);
});

it('gives a stored run a social contribution and mentions friends when it is strong', function () {
    $user = User::factory()->create();
    foreach (range(1, 6) as $i) {
        friendEntry($user, 1000 + $i);
    }
    $popular = friendEntry($user, 440);
    activity($user, 440, 3);

    $run = app(RunRecommendations::class)($user);
    $pick = $run?->picks->firstWhere('library_entry_id', $popular->id);

    expect($pick)->not->toBeNull()
        ->and($pick->contributions['social']['value'])->toEqual(1.0)
        ->and($pick->explanation)->toContain('3 friends');
});

it('keeps the social contribution null in a stored run without friend activity', function () {
    $user = User::factory()->create();
    friendEntry($user, 440);

    $pick = app(RunRecommendations::class)($user)?->picks->first();

    expect($pick->contributions['social']['value'])->toBeNull();
});
