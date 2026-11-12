<?php

use App\Actions\DeleteAccount;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\RecommendationPick;
use App\Models\RecommendationRun;
use App\Models\Tag;
use App\Models\User;
use App\Recommendations\LatestRecommendations;
use App\Recommendations\RunRecommendations;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC'));
});

/**
 * A user with a varied library: played and unplayed matched games, one finished game, one unmatched game.
 *
 * @return array{user: User, finished: LibraryEntry, unmatched: LibraryEntry}
 */
function seedLibrary(int $matched = 25): array
{
    $user = User::factory()->create();
    $tags = Tag::factory()->count(8)->create();

    foreach (range(1, $matched) as $i) {
        $game = Game::factory()->matched()->create(['ttb_normally' => 1_000_000]);
        $game->tags()->attach($tags->random(3)->pluck('id'));
        LibraryEntry::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'playtime_minutes' => $i % 3 === 0 ? 0 : 60 * $i,
            'playtime_2weeks_minutes' => null,
            'last_played_at' => $i % 3 === 0 ? null : now()->subDays(40 + $i),
        ]);
    }

    $finished = LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->matched()->create(['ttb_normally' => 36_000])->id,
        'playtime_minutes' => 60 * 30,
        'playtime_2weeks_minutes' => null,
        'last_played_at' => now()->subDays(100),
    ]);
    $unmatched = LibraryEntry::factory()->create([
        'user_id' => $user->id,
        'game_id' => Game::factory()->unmatched()->create()->id,
        'playtime_minutes' => 0,
        'playtime_2weeks_minutes' => null,
        'last_played_at' => null,
    ]);

    return ['user' => $user, 'finished' => $finished, 'unmatched' => $unmatched];
}

describe('RunRecommendations', function () {
    it('stores a run with at most 20 picks in rank order', function () {
        ['user' => $user] = seedLibrary();

        $run = app(RunRecommendations::class)($user);
        $scores = $run->picks->pluck('score')->all();

        expect($run)->toBeInstanceOf(RecommendationRun::class)
            ->and($run->user_id)->toBe($user->id)
            ->and($run->candidate_count)->toBe(25)
            ->and($run->picks)->toHaveCount(20)
            ->and($run->picks->pluck('rank')->all())->toBe(range(1, 20))
            ->and($scores)->toBe(collect($scores)->sortDesc()->values()->all())
            ->and(RecommendationPick::count())->toBe(20);
    });

    it('stores every signal contribution and a non-empty explanation per pick', function () {
        ['user' => $user] = seedLibrary();

        $run = app(RunRecommendations::class)($user);

        foreach ($run->picks as $pick) {
            expect(array_keys($pick->contributions))->toBe(['taste', 'momentum', 'fit', 'social', 'freshness', 'mood', 'mismatch', 'snooze'])
                ->and($pick->contributions['fit'])->toHaveKeys(['value', 'weight', 'weighted'])
                ->and($pick->contributions['social']['value'])->toBeNull()
                ->and($pick->explanation)->not->toBe('');
        }
    });

    it('snapshots the weights used', function () {
        ['user' => $user] = seedLibrary(3);
        config(['recommender.weights.taste' => 0.4]);

        $run = app(RunRecommendations::class)($user);

        expect($run->fresh()->weights)->toBe(array_merge(config('recommender.weights'), ['taste' => 0.4]));
    });

    it('returns null and stores nothing when there are no candidates', function () {
        $empty = User::factory()->create();
        $unmatchedOnly = User::factory()->create();
        LibraryEntry::factory()->create([
            'user_id' => $unmatchedOnly->id,
            'game_id' => Game::factory()->unmatched()->create()->id,
        ]);

        expect(app(RunRecommendations::class)($empty))->toBeNull()
            ->and(app(RunRecommendations::class)($unmatchedOnly))->toBeNull()
            ->and(RecommendationRun::count())->toBe(0);
    });

    it('puts a matched, unfinished game on top for a seeded user', function () {
        ['user' => $user, 'finished' => $finished, 'unmatched' => $unmatched] = seedLibrary();

        $run = app(RunRecommendations::class)($user);
        $top = $run->picks->first()->libraryEntry;

        expect($top->game->match_status->value)->toBe('matched')
            ->and($run->picks->pluck('library_entry_id')->all())->not->toContain($finished->id, $unmatched->id)
            ->and($top->playtime_minutes * 60)->toBeLessThan($top->game->ttb_normally);
    });
});

describe('LatestRecommendations', function () {
    it('reuses a fresh run', function () {
        ['user' => $user] = seedLibrary(5);
        $first = app(LatestRecommendations::class)($user);

        $this->travelTo(now()->addHours(5));
        $second = app(LatestRecommendations::class)($user);

        expect($second->id)->toBe($first->id)
            ->and($second->picks)->toHaveCount(5)
            ->and(RecommendationRun::count())->toBe(1);
    });

    it('recomputes when the run is older than 6 hours', function () {
        ['user' => $user] = seedLibrary(5);
        $first = app(LatestRecommendations::class)($user);

        $this->travelTo(now()->addHours(6)->addSecond());
        $second = app(LatestRecommendations::class)($user);

        expect($second->id)->not->toBe($first->id)
            ->and(RecommendationRun::count())->toBe(2);
    });

    it('recomputes when the library was imported after the run', function () {
        ['user' => $user] = seedLibrary(5);
        $first = app(LatestRecommendations::class)($user);

        $this->travelTo(now()->addMinutes(10));
        $user->update(['imported_at' => now()]);

        $second = app(LatestRecommendations::class)($user->fresh());

        expect($second->id)->not->toBe($first->id);
    });

    it('reuses a run that is newer than the last import', function () {
        ['user' => $user] = seedLibrary(5);
        $user->update(['imported_at' => now()->subHour()]);
        $first = app(LatestRecommendations::class)($user);

        expect(app(LatestRecommendations::class)($user->fresh())->id)->toBe($first->id);
    });

    it('returns null and stores nothing when there are no candidates', function () {
        $user = User::factory()->create();

        expect(app(LatestRecommendations::class)($user))->toBeNull()
            ->and(RecommendationRun::count())->toBe(0);
    });
});

it('deletes runs and picks with the account', function () {
    ['user' => $user] = seedLibrary(5);
    ['user' => $other] = seedLibrary(3);
    app(RunRecommendations::class)($user);
    app(RunRecommendations::class)($other);

    (new DeleteAccount)($user);

    expect(RecommendationRun::where('user_id', $user->id)->count())->toBe(0)
        ->and(RecommendationRun::count())->toBe(1)
        ->and(RecommendationPick::count())->toBe(3);
});

it('relates runs, picks and entries', function () {
    $pick = RecommendationPick::factory()->create();

    expect($pick->run->user->recommendationRuns->first()->is($pick->run))->toBeTrue()
        ->and($pick->libraryEntry)->toBeInstanceOf(LibraryEntry::class)
        ->and($pick->run->picks->first()->is($pick))->toBeTrue()
        ->and($pick->run->getUpdatedAtColumn())->toBeNull();
});
