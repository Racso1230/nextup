<?php

use App\Enums\FeedbackKind;
use App\Enums\TagType;
use App\Recommender\Candidates;
use App\Recommender\Company;
use App\Recommender\Contribution;
use App\Recommender\Explainer;
use App\Recommender\Feedback;
use App\Recommender\GameFacts;
use App\Recommender\Mood;
use App\Recommender\Profile;
use App\Recommender\Ranked;
use App\Recommender\RecommendationContext;
use App\Recommender\Recommender;
use App\Recommender\SessionContext;
use App\Recommender\SignalName;
use App\Recommender\Signals\FitSignal;
use App\Recommender\Signals\MomentumSignal;
use App\Recommender\Signals\MoodSignal;
use App\Recommender\Signals\SnoozeSignal;
use App\Recommender\Signals\SocialSignal;
use App\Recommender\TagRef;
use App\Recommender\Weights;
use Tests\Unit\Recommender\Support\Facts;

/**
 * Phase 4 behaviour from docs/recommender.md, "Session context and feedback".
 *
 * @param  list<GameFacts>  $library
 * @param  array<int, Feedback>  $feedback
 */
function sessionContext(array $library, ?SessionContext $session = null, array $feedback = []): RecommendationContext
{
    $config = require __DIR__.'/../../../config/recommender.php';

    return new RecommendationContext($library, Facts::now(), Weights::fromArray($config['weights']), $session ?? new SessionContext, $feedback);
}

function sessionProfile(?SessionContext $session = null, array $feedback = []): Profile
{
    return new Profile([], [], [], [], [], 0, Facts::now(), $session ?? new SessionContext, $feedback);
}

function feedbackHoursAgo(FeedbackKind $kind, float $hours): Feedback
{
    return new Feedback($kind, Facts::now()->modify('-'.(int) round($hours * 60).' minutes'));
}

function mode(int $id): TagRef
{
    return new TagRef(TagType::GameMode, $id, "mode {$id}");
}

/*
| Time available
*/

it('ignores fit until a time is chosen, then scales the half-point with the time available', function () {
    $game = Facts::game(1, ttbHours: 10); // 10 hours remaining

    expect((new FitSignal)->score($game, sessionProfile()))->toBeNull()
        ->and((new FitSignal)->score($game, sessionProfile(new SessionContext(2.0))))->toEqualWithDelta(0.5, 1e-9) // half-point 10
        ->and((new FitSignal)->score($game, sessionProfile(new SessionContext(1.0))))->toEqualWithDelta(1 / 3, 1e-9) // half-point 5
        ->and((new FitSignal)->score($game, sessionProfile(new SessionContext(4.0))))->toEqualWithDelta(2 / 3, 1e-9); // half-point 20
});

it('keeps unknown and past time to beat neutral whatever the time available', function () {
    $short = new SessionContext(1.0);

    expect((new FitSignal)->score(Facts::game(1), sessionProfile($short)))->toBe(0.5)
        ->and((new FitSignal)->score(Facts::game(2, minutes: 900, ttbHours: 10), sessionProfile($short)))->toBe(0.5);
});

it('rejects non-positive hours', function () {
    new SessionContext(0.0);
})->throws(InvalidArgumentException::class);

/*
| Mood
*/

it('is null for everyone when no mood is picked', function () {
    expect((new MoodSignal)->score(Facts::game(1, tags: [Facts::genre(5)]), sessionProfile()))->toBeNull();
});

it('scores the share of mood tags in the chosen list', function () {
    // Shooter (intense) + Puzzle (chill) + Horror (intense): intense 2/3, chill 1/3.
    $mixed = Facts::game(1, tags: [Facts::genre(5), Facts::genre(9), Facts::theme(19)]);

    expect((new MoodSignal)->score($mixed, sessionProfile(new SessionContext(mood: Mood::Intense))))->toEqualWithDelta(2 / 3, 1e-9)
        ->and((new MoodSignal)->score($mixed, sessionProfile(new SessionContext(mood: Mood::Chill))))->toEqualWithDelta(1 / 3, 1e-9);
});

it('is a neutral 0.5 for a game with no mood tags', function () {
    // RPG + Fantasy are in neither list.
    $neutral = Facts::game(1, tags: [Facts::genre(12), Facts::theme(17)]);

    expect((new MoodSignal)->score($neutral, sessionProfile(new SessionContext(mood: Mood::Chill))))->toBe(0.5);
});

it('counts Battle Royale as intense', function () {
    expect((new MoodSignal)->score(Facts::game(1, tags: [mode(6)]), sessionProfile(new SessionContext(mood: Mood::Intense))))->toBe(1.0);
});

/*
| Solo or with friends
*/

it('filters by game mode and never hides a game without mode data', function () {
    $singleOnly = Facts::game(1, tags: [mode(1)]);
    $multiOnly = Facts::game(2, tags: [mode(2)]);
    $both = Facts::game(3, tags: [mode(1), mode(3)]);
    $noModes = Facts::game(4, tags: [Facts::genre(5)]);
    $library = [$singleOnly, $multiOnly, $both, $noModes];

    $ids = fn (?Company $company) => array_map(
        fn ($g) => $g->entryId,
        Candidates::select(sessionContext($library, new SessionContext(company: $company))),
    );

    expect($ids(Company::Friends))->toEqualCanonicalizing([2, 3, 4])
        ->and($ids(Company::Solo))->toEqualCanonicalizing([1, 3, 4])
        ->and($ids(null))->toEqualCanonicalizing([1, 2, 3, 4]);
});

/*
| Feedback
*/

it('hides "never" games for good and "not tonight" games for 24 hours', function () {
    $library = [Facts::game(1), Facts::game(2), Facts::game(3), Facts::game(4)];
    $feedback = [
        1 => feedbackHoursAgo(FeedbackKind::Never, 24 * 365),
        2 => feedbackHoursAgo(FeedbackKind::NotTonight, 23),
        3 => feedbackHoursAgo(FeedbackKind::NotTonight, 25),
        4 => feedbackHoursAgo(FeedbackKind::Playing, 1),
    ];

    $ids = array_map(fn ($g) => $g->entryId, Candidates::select(sessionContext($library, feedback: $feedback)));

    expect($ids)->toEqualCanonicalizing([3, 4]);
});

it('fades "not tonight" back with a snooze penalty after the first day', function () {
    $snooze = fn (float $days) => (new SnoozeSignal)->score(
        Facts::game(1),
        sessionProfile(feedback: [1 => feedbackHoursAgo(FeedbackKind::NotTonight, $days * 24)]),
    );

    expect($snooze(1))->toEqualWithDelta(1.0, 1e-6)
        ->and($snooze(3))->toEqualWithDelta(exp(-1), 1e-6)   // 0.37
        ->and($snooze(7))->toEqualWithDelta(exp(-3), 1e-6)   // 0.05
        ->and((new SnoozeSignal)->score(Facts::game(1), sessionProfile()))->toBe(0.0)
        ->and(SignalName::Snooze->isPenalty())->toBeTrue();
});

it('treats "playing it" as full momentum for three days, even for an unplayed game', function () {
    $momentum = fn (float $hours) => (new MomentumSignal)->score(
        Facts::game(1),
        sessionProfile(feedback: [1 => feedbackHoursAgo(FeedbackKind::Playing, $hours)]),
    );

    expect($momentum(2))->toBe(1.0)
        ->and($momentum(71))->toBe(1.0)
        ->and($momentum(73))->toBe(0.0);
});

it('changes the ranking when the user says "not tonight" to the top pick', function () {
    $library = [
        Facts::game(1, minutes: 600, lastPlayed: Facts::daysAgo(1), tags: [Facts::genre(5)]),
        Facts::game(2, minutes: 600, lastPlayed: Facts::daysAgo(20), tags: [Facts::genre(5)]),
        Facts::game(3, tags: [Facts::genre(5)]),
    ];

    $before = (new Recommender)->rank(sessionContext($library));
    $topId = $before[0]->game->entryId;
    $after = (new Recommender)->rank(sessionContext($library, feedback: [$topId => feedbackHoursAgo(FeedbackKind::NotTonight, 1)]));

    expect(array_map(fn ($r) => $r->game->entryId, $after))->not->toContain($topId);
});

/*
| Social
*/

it('scores friends who played it recently, and stays null when unknown', function () {
    $withFriends = fn (?int $n) => (new SocialSignal)->score(
        new GameFacts(1, 1001, 'Game 1', 0, null, null, null, null, [], $n),
        sessionProfile(),
    );

    expect($withFriends(null))->toBeNull()
        ->and($withFriends(0))->toBe(0.0)
        ->and($withFriends(1))->toEqualWithDelta(1 / 3, 1e-9)
        ->and($withFriends(5))->toBe(1.0);
});

/*
| Explanations
*/

it('explains playing, friends and mood, and never mentions a snooze', function () {
    $weights = ['taste' => 0.35, 'momentum' => 0.2, 'fit' => 0.15, 'social' => 0.1, 'freshness' => 0.1, 'mood' => 0.15, 'mismatch' => 0.1, 'snooze' => 0.2];
    $values = ['momentum' => 1.0, 'social' => 2 / 3, 'mood' => 1.0, 'snooze' => 0.9];
    $contributions = array_map(
        fn (SignalName $s) => Contribution::of($s, $values[$s->value] ?? null, $weights[$s->value]),
        SignalName::cases(),
    );
    $game = new GameFacts(1, 1001, 'Stardew Valley', 0, null, null, null, null, [Facts::genre(13)], 2);
    $ranked = new Ranked($game, 0.5, $contributions, [], [], feedbackHoursAgo(FeedbackKind::Playing, 5));

    expect((new Explainer)->explain($ranked, Facts::now()))
        ->toBe('You said you\'re playing it, it suits a chill evening, and 2 friends played it in the last two weeks.');
});
