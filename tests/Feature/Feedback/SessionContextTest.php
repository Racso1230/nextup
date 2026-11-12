<?php

use App\Enums\ImportStatus;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\RecommendationRun;
use App\Models\Tag;
use App\Models\User;
use App\Recommendations\LatestRecommendations;
use App\Recommender\Company;
use App\Recommender\Mood;
use App\Recommender\SessionContext;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
});

/**
 * A user with $count matched, unfinished games that have never been played together.
 */
function contextUser(int $count = 6): User
{
    $user = User::factory()->create(['import_status' => ImportStatus::Done]);
    $tags = Tag::factory()->count(6)->create();

    foreach (range(1, $count) as $i) {
        $game = Game::factory()->matched()->create(['ttb_normally' => 1_000_000]);
        $game->tags()->attach($tags->random(2)->pluck('id'));
        LibraryEntry::factory()->create([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'playtime_minutes' => 60 * $i,
            'playtime_2weeks_minutes' => null,
            'last_played_at' => now()->subDays(40 + $i),
        ]);
    }

    return $user;
}

/**
 * @return list<int>
 */
function playNextEntryIds(User $user, array $params = []): array
{
    /** @var list<array{entry_id: int}> $picks */
    $picks = test()->actingAs($user)->get(route('play-next', $params))->inertiaProps('picks');

    return array_column($picks, 'entry_id');
}

describe('session context from the query string', function () {
    it('maps valid values onto the filters prop', function () {
        $user = contextUser();

        $this->actingAs($user)->get(route('play-next', ['hours' => 4, 'mood' => 'intense', 'with' => 'friends']))
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => 4, 'mood' => 'intense', 'with' => 'friends']));
    });

    it('defaults to 2 hours, no mood and no company', function () {
        $this->actingAs(contextUser())->get(route('play-next'))
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => 2, 'mood' => null, 'with' => null]));
    });

    it('falls back to the defaults for invalid values instead of erroring', function (array $params) {
        $this->actingAs(contextUser())->get(route('play-next', $params))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => 2, 'mood' => null, 'with' => null]));
    })->with([
        'unknown hours' => [['hours' => 3, 'mood' => 'sleepy', 'with' => 'dog']],
        'non numeric hours' => [['hours' => 'lots']],
        'zero hours' => [['hours' => 0]],
        'arrays' => [['hours' => [1], 'mood' => ['chill'], 'with' => ['solo']]],
    ]);

    it('keeps each choice independent of the others', function () {
        $this->actingAs(contextUser())->get(route('play-next', ['hours' => 1, 'mood' => 'nope', 'with' => 'solo']))
            ->assertInertia(fn (Assert $page) => $page->where('filters', ['hours' => 1, 'mood' => null, 'with' => 'solo']));
    });

    it('stores the context on the run it ranked', function () {
        $user = contextUser();

        $this->actingAs($user)->get(route('play-next', ['hours' => 6, 'mood' => 'chill', 'with' => 'solo']))->assertOk();

        expect(RecommendationRun::firstOrFail()->context)->toEqual(['hours' => 6.0, 'mood' => 'chill', 'company' => 'solo']);
    });

    it('stores the default context as 2 hours with no mood or company', function () {
        $user = contextUser();

        $this->actingAs($user)->get(route('play-next'))->assertOk();

        expect(RecommendationRun::firstOrFail()->context)->toEqual(['hours' => 2.0, 'mood' => null, 'company' => null]);
    });
});

describe('the mood bar', function () {
    it('is on the top pick when a mood is chosen', function () {
        $keys = collect($this->actingAs(contextUser())->get(route('play-next', ['mood' => 'chill']))->inertiaProps('picks.0.signals'))
            ->firstWhere('key', 'mood');

        expect($keys)->toMatchArray(['key' => 'mood', 'label' => 'Mood match']);
    });

    it('is absent when no mood is chosen', function () {
        $signals = $this->actingAs(contextUser())->get(route('play-next'))->inertiaProps('picks.0.signals');

        expect(array_column($signals, 'key'))->not->toContain('mood', 'snooze', 'mismatch');
    });
});

describe('caching by context', function () {
    it('reuses the run for the same context', function () {
        $user = contextUser();
        $session = new SessionContext(4.0, Mood::Chill, Company::Solo);
        $first = app(LatestRecommendations::class)($user, $session);

        $this->travelTo(now()->addHour());
        $second = app(LatestRecommendations::class)($user, new SessionContext(4.0, Mood::Chill, Company::Solo));

        expect($second?->id)->toBe($first?->id)
            ->and(RecommendationRun::count())->toBe(1);
    });

    it('ranks afresh for a different context', function (SessionContext $other) {
        $user = contextUser();
        $first = app(LatestRecommendations::class)($user, new SessionContext);

        $second = app(LatestRecommendations::class)($user, $other);

        expect($second?->id)->not->toBe($first?->id)
            ->and($second?->context)->toEqual($other->toArray())
            ->and(RecommendationRun::count())->toBe(2);
    })->with([
        'hours' => fn () => new SessionContext(4.0),
        'mood' => fn () => new SessionContext(mood: Mood::Intense),
        'company' => fn () => new SessionContext(company: Company::Friends),
    ]);

    it('ranks afresh when the newest run has no stored context', function () {
        $user = contextUser();
        RecommendationRun::factory()->create(['user_id' => $user->id, 'context' => null]);

        $run = app(LatestRecommendations::class)($user, new SessionContext);

        expect(RecommendationRun::count())->toBe(2)
            ->and($run?->context)->toEqual(['hours' => 2.0, 'mood' => null, 'company' => null]);
    });

    it('ranks afresh when feedback changed after the run', function () {
        $user = contextUser();
        $first = app(LatestRecommendations::class)($user);
        $this->travelTo(now()->addMinute());
        $user->forceFill(['feedback_changed_at' => now()])->save();

        $second = app(LatestRecommendations::class)($user);

        expect($second?->id)->not->toBe($first?->id);
    });

    it('ranks afresh when feedback changed in the same second as the run', function () {
        $user = contextUser();
        $first = app(LatestRecommendations::class)($user);
        $user->forceFill(['feedback_changed_at' => now()])->save();

        expect(app(LatestRecommendations::class)($user)?->id)->not->toBe($first?->id);
    });

    it('reuses the run when feedback last changed before it', function () {
        $user = contextUser();
        $user->forceFill(['feedback_changed_at' => now()->subHour()])->save();
        $first = app(LatestRecommendations::class)($user);
        $this->travelTo(now()->addMinute());

        expect(app(LatestRecommendations::class)($user)?->id)->toBe($first?->id);
    });

    it('ignores other users\' feedback', function () {
        $user = contextUser();
        $first = app(LatestRecommendations::class)($user);
        $this->travelTo(now()->addMinute());
        $other = User::factory()->create();
        PickFeedback::factory()->create(['user_id' => $other->id]);

        expect(app(LatestRecommendations::class)($user)?->id)->toBe($first?->id);
    });
});

it('puts a different game on top after "not tonight" on the current top pick', function () {
    $user = contextUser();

    $before = playNextEntryIds($user);
    $top = $before[0];

    $this->actingAs($user)->post(route('picks.feedback', $top), ['kind' => 'not_tonight'])->assertRedirect();

    $after = playNextEntryIds($user);

    expect($after[0])->not->toBe($top)
        ->and($after)->not->toContain($top);
});

it('drops a "never" game from the picks until the feedback is undone', function () {
    $user = contextUser();
    $top = playNextEntryIds($user)[0];

    $this->actingAs($user)->post(route('picks.feedback', $top), ['kind' => 'never']);
    $this->travelTo(now()->addDays(30));
    expect(playNextEntryIds($user))->not->toContain($top);

    $runs = RecommendationRun::count();
    $this->travelTo(now()->addMinute());
    $this->actingAs($user)->delete(route('picks.feedback.destroy', $top));

    expect(playNextEntryIds($user))->toContain($top)
        ->and(RecommendationRun::count())->toBe($runs + 1);
});
