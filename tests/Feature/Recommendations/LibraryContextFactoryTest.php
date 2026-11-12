<?php

use App\Enums\FeedbackKind;
use App\Enums\TagType;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\PlaytimeSnapshot;
use App\Models\Tag;
use App\Models\User;
use App\Recommendations\LibraryContextFactory;
use App\Recommender\Company;
use App\Recommender\GameFacts;
use App\Recommender\Mood;
use App\Recommender\SessionContext;
use App\Recommender\SignalName;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->now = CarbonImmutable::parse('2026-11-11 12:00:00', 'UTC');
    $this->travelTo($this->now);
});

/**
 * @return list<GameFacts>
 */
function contextFor(User $user, CarbonImmutable $now): array
{
    return app(LibraryContextFactory::class)->build($user, $now)->library;
}

/**
 * @param  list<GameFacts>  $library
 */
function factsFor(array $library, LibraryEntry $entry): GameFacts
{
    foreach ($library as $facts) {
        if ($facts->entryId === $entry->id) {
            return $facts;
        }
    }

    throw new RuntimeException('Entry not in the context.');
}

/**
 * @param  array<string, mixed>  $attributes
 */
function entryFor(User $user, array $attributes = [], ?Game $game = null): LibraryEntry
{
    return LibraryEntry::factory()->create(array_merge([
        'user_id' => $user->id,
        'game_id' => ($game ?? Game::factory()->matched()->create())->id,
    ], $attributes));
}

function snapshot(LibraryEntry $entry, string $date, int $minutes): void
{
    PlaytimeSnapshot::factory()->create([
        'library_entry_id' => $entry->id,
        'captured_on' => $date,
        'minutes_total' => $minutes,
    ]);
}

it('maps entries to game facts', function () {
    $user = User::factory()->create();
    $game = Game::factory()->matched()->create(['name' => 'Hades', 'ttb_normally' => 72_000]);
    $entry = entryFor($user, [
        'playtime_minutes' => 600,
        'playtime_2weeks_minutes' => 90,
        'last_played_at' => '2026-11-01 08:30:00',
    ], $game);

    $facts = factsFor(contextFor($user, $this->now), $entry);

    expect($facts->igdbId)->toBe($game->igdb_id)
        ->and($facts->name)->toBe('Hades')
        ->and($facts->playtimeMinutes)->toBe(600)
        ->and($facts->playtime2WeeksMinutes)->toBe(90)
        ->and($facts->ttbNormallySeconds)->toBe(72_000)
        ->and($facts->lastPlayedAt)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($facts->lastPlayedAt->format('Y-m-d H:i:s'))->toBe('2026-11-01 08:30:00');
});

it('only gives matched games an igdb id', function () {
    $user = User::factory()->create();
    $matched = entryFor($user);
    $unmatched = entryFor($user, [], Game::factory()->unmatched()->create(['igdb_id' => 777]));
    $pending = entryFor($user, [], Game::factory()->create());

    $library = contextFor($user, $this->now);

    expect(factsFor($library, $matched)->igdbId)->not->toBeNull()
        ->and(factsFor($library, $unmatched)->igdbId)->toBeNull()
        ->and(factsFor($library, $pending)->igdbId)->toBeNull();
});

it('keeps an unknown time to beat and a missing last played date as null', function () {
    $user = User::factory()->create();
    $entry = entryFor($user, ['last_played_at' => null], Game::factory()->matched()->create(['ttb_normally' => null]));

    $facts = factsFor(contextFor($user, $this->now), $entry);

    expect($facts->ttbNormallySeconds)->toBeNull()
        ->and($facts->lastPlayedAt)->toBeNull();
});

it('includes tags of every type, keywords too', function () {
    $user = User::factory()->create();
    $game = Game::factory()->matched()->create();
    $game->tags()->attach([
        Tag::factory()->genre()->create(['igdb_id' => 12, 'name' => 'Roguelike'])->id,
        Tag::factory()->theme()->create(['igdb_id' => 12, 'name' => 'Fantasy'])->id,
        Tag::factory()->gameMode()->create()->id,
        Tag::factory()->perspective()->create()->id,
        Tag::factory()->keyword()->create()->id,
    ]);
    $entry = entryFor($user, [], $game);

    $tags = factsFor(contextFor($user, $this->now), $entry)->tags;

    expect(collect($tags)->map(fn ($tag) => $tag->type->value)->all())
        ->toEqualCanonicalizing(array_map(fn (TagType $type) => $type->value, TagType::cases()))
        ->and(collect($tags)->map(fn ($tag) => $tag->key())->all())->toContain('genre:12', 'theme:12');
});

it('only loads the given user entries and uses the configured weights', function () {
    $user = User::factory()->create();
    entryFor($user);
    entryFor(User::factory()->create());
    config(['recommender.weights.taste' => 0.5]);

    $context = app(LibraryContextFactory::class)->build($user, $this->now);

    expect($context->library)->toHaveCount(1)
        ->and($context->now)->toBe($this->now)
        ->and($context->weights->for(SignalName::Taste))->toBe(0.5);
});

describe('recent minutes from snapshots', function () {
    it('is null for everyone when snapshots do not cover the window', function () {
        $user = User::factory()->create();
        $entry = entryFor($user, ['playtime_minutes' => 500]);
        // Earliest snapshot is 13 days ago: one day short of the 14-day window.
        snapshot($entry, '2026-10-29', 300);
        snapshot($entry, '2026-11-10', 450);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBeNull();
    });

    it('is null when there are no snapshots at all', function () {
        $user = User::factory()->create();
        $entry = entryFor($user);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBeNull();
    });

    it('is current minutes minus the minutes as of 14 days ago', function () {
        $user = User::factory()->create();
        $entry = entryFor($user, ['playtime_minutes' => 1_500]);
        // Exactly 14 days ago counts as covered; the latest row on or before 2026-10-28 is the 10-28 one.
        snapshot($entry, '2026-10-20', 900);
        snapshot($entry, '2026-10-28', 1_000);
        snapshot($entry, '2026-11-05', 1_300);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBe(500);
    });

    it('uses the latest earlier snapshot when the cutoff day has no row', function () {
        $user = User::factory()->create();
        $entry = entryFor($user, ['playtime_minutes' => 1_200]);
        snapshot($entry, '2026-10-01', 800);
        snapshot($entry, '2026-10-27', 1_000);
        snapshot($entry, '2026-10-30', 1_100);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBe(200);
    });

    it('is null for an entry first seen inside the window, but not for its neighbours', function () {
        $user = User::factory()->create();
        $old = entryFor($user, ['playtime_minutes' => 700]);
        $new = entryFor($user, ['playtime_minutes' => 200]);
        snapshot($old, '2026-10-10', 600);
        snapshot($new, '2026-11-05', 100);

        $library = contextFor($user, $this->now);

        expect(factsFor($library, $old)->recentMinutesFromSnapshots)->toBe(100)
            ->and(factsFor($library, $new)->recentMinutesFromSnapshots)->toBeNull();
    });

    it('never goes below zero', function () {
        $user = User::factory()->create();
        $entry = entryFor($user, ['playtime_minutes' => 400]);
        // Steam can revise a total downwards; the result is clamped.
        snapshot($entry, '2026-10-01', 900);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBe(0);
    });

    it('ignores other users snapshots when judging coverage', function () {
        $user = User::factory()->create();
        $entry = entryFor($user);
        snapshot(entryFor(User::factory()->create()), '2026-09-01', 100);
        snapshot($entry, '2026-11-08', 100);

        expect(factsFor(contextFor($user, $this->now), $entry)->recentMinutesFromSnapshots)->toBeNull();
    });
});

it('loads a big tagged library in a bounded number of queries', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->count(12)->create();

    Game::factory()->matched()->count(100)->create()->each(function (Game $game) use ($user, $tags) {
        $game->tags()->attach($tags->random(5)->pluck('id'));
        $entry = entryFor($user, [], $game);
        snapshot($entry, '2026-10-01', 10);
    });

    DB::enableQueryLog();
    $library = contextFor($user, $this->now);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($library)->toHaveCount(100)
        ->and($library[0]->tags)->toHaveCount(5)
        ->and($queries)->toBeLessThanOrEqual(6);
});

describe('session and feedback', function () {
    it('passes the session into the context and defaults it', function () {
        $user = User::factory()->create();
        $session = new SessionContext(4.0, Mood::Chill, Company::Friends);

        $given = app(LibraryContextFactory::class)->build($user, $this->now, $session);
        $default = app(LibraryContextFactory::class)->build($user, $this->now);

        expect($given->session)->toBe($session)
            ->and($default->session->toArray())->toBe(SessionContext::default()->toArray());
    });

    it('loads the latest feedback per entry and nothing else', function () {
        $user = User::factory()->create();
        $first = entryFor($user);
        $second = entryFor($user);
        $untouched = entryFor($user);
        $other = entryFor(User::factory()->create());
        $at = fn (string $time) => CarbonImmutable::parse($time, 'UTC');
        $say = fn (LibraryEntry $entry, FeedbackKind $kind, CarbonImmutable $when, ?User $by = null) => PickFeedback::factory()->create([
            'user_id' => ($by ?? $user)->id, 'library_entry_id' => $entry->id, 'kind' => $kind, 'created_at' => $when,
        ]);
        $say($first, FeedbackKind::Never, $at('2026-11-01 10:00:00'));
        $say($first, FeedbackKind::NotTonight, $at('2026-11-10 10:00:00'));
        $say($first, FeedbackKind::Playing, $at('2026-11-05 10:00:00'));
        $say($second, FeedbackKind::Never, $at('2026-11-09 08:30:00'));
        $say($other, FeedbackKind::Never, $at('2026-11-09 08:30:00'), $other->user);

        $feedback = app(LibraryContextFactory::class)->build($user, $this->now)->feedback;

        expect(array_keys($feedback))->toEqualCanonicalizing([$first->id, $second->id])
            ->and($feedback[$first->id]->kind)->toBe(FeedbackKind::NotTonight)
            ->and($feedback[$first->id]->at->format('Y-m-d H:i:s'))->toBe('2026-11-10 10:00:00')
            ->and($feedback[$second->id]->kind)->toBe(FeedbackKind::Never)
            ->and($feedback)->not->toHaveKey($untouched->id);
    });

    it('keeps the query count bounded with feedback present', function () {
        $user = User::factory()->create();
        $tags = Tag::factory()->count(12)->create();

        Game::factory()->matched()->count(100)->create()->each(function (Game $game) use ($user, $tags) {
            $game->tags()->attach($tags->random(5)->pluck('id'));
            $entry = entryFor($user, [], $game);
            snapshot($entry, '2026-10-01', 10);
            PickFeedback::factory()->count(2)->create(['user_id' => $user->id, 'library_entry_id' => $entry->id]);
        });

        DB::enableQueryLog();
        $context = app(LibraryContextFactory::class)->build($user, $this->now);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($context->feedback)->toHaveCount(100)
            ->and($queries)->toBeLessThanOrEqual(7);
    });
});
