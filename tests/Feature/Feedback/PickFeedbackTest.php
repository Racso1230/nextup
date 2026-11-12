<?php

use App\Actions\DeleteAccount;
use App\Enums\FeedbackKind;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\RecommendationRun;
use App\Models\User;
use Carbon\CarbonImmutable;

function feedbackEntry(User $user): LibraryEntry
{
    return LibraryEntry::factory()->create(['user_id' => $user->id, 'game_id' => Game::factory()->matched()->create()->id]);
}

describe('recording feedback', function () {
    it('creates a row for each kind', function (FeedbackKind $kind) {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);

        $this->actingAs($user)->post(route('picks.feedback', $entry), ['kind' => $kind->value])->assertRedirect();

        $row = PickFeedback::firstOrFail();
        expect($row->user_id)->toBe($user->id)
            ->and($row->library_entry_id)->toBe($entry->id)
            ->and($row->kind)->toBe($kind)
            ->and($row->created_at)->not->toBeNull();
    })->with(FeedbackKind::cases());

    it('sets feedback_changed_at', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        expect($user->feedback_changed_at)->toBeNull();

        $this->actingAs($user)->post(route('picks.feedback', $entry), ['kind' => 'playing']);

        expect($user->fresh()->feedback_changed_at?->toIso8601String())->toBe(now()->toIso8601String());
    });

    it('keeps every click as its own row', function () {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);

        $this->actingAs($user)->post(route('picks.feedback', $entry), ['kind' => 'not_tonight']);
        $this->actingAs($user)->post(route('picks.feedback', $entry), ['kind' => 'never']);

        expect(PickFeedback::count())->toBe(2);
    });

    it('answers 404 for another user\'s entry and records nothing', function () {
        $user = User::factory()->create();
        $theirs = feedbackEntry(User::factory()->create());

        $this->actingAs($user)->post(route('picks.feedback', $theirs), ['kind' => 'never'])->assertNotFound();

        expect(PickFeedback::count())->toBe(0);
    });

    it('answers 404 for an entry that does not exist', function () {
        $this->actingAs(User::factory()->create())
            ->post(route('picks.feedback', 999_999), ['kind' => 'never'])
            ->assertNotFound();
    });

    it('redirects guests to the login page', function () {
        $entry = feedbackEntry(User::factory()->create());

        $this->post(route('picks.feedback', $entry), ['kind' => 'never'])->assertRedirect(route('login'));

        expect(PickFeedback::count())->toBe(0);
    });

    it('rejects an invalid or missing kind', function (mixed $kind) {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);

        $this->actingAs($user)
            ->from(route('play-next'))
            ->post(route('picks.feedback', $entry), $kind === null ? [] : ['kind' => $kind])
            ->assertSessionHasErrors('kind');

        expect(PickFeedback::count())->toBe(0);
    })->with(['bogus', '', null]);

    it('redirects back keeping the query string', function () {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        $from = route('play-next', ['hours' => 4, 'mood' => 'chill', 'with' => 'solo']);

        $this->actingAs($user)->from($from)->post(route('picks.feedback', $entry), ['kind' => 'not_tonight'])
            ->assertRedirect($from);
    });

    it('flashes the toast text for each kind', function (FeedbackKind $kind, string $message) {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);

        $this->actingAs($user)->post(route('picks.feedback', $entry), ['kind' => $kind->value])
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => $message]);
    })->with([
        [FeedbackKind::Playing, "Have fun. We'll keep it near the top for a few days."],
        [FeedbackKind::NotTonight, 'Hidden for tonight.'],
        [FeedbackKind::Never, "We won't suggest it again. You can undo this in your library."],
    ]);
});

describe('undoing feedback', function () {
    it('deletes every row for that entry only', function () {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        $other = feedbackEntry($user);
        PickFeedback::factory()->count(2)->create(['user_id' => $user->id, 'library_entry_id' => $entry->id]);
        PickFeedback::factory()->create(['user_id' => $user->id, 'library_entry_id' => $other->id]);

        $this->actingAs($user)->delete(route('picks.feedback.destroy', $entry))->assertRedirect();

        expect(PickFeedback::where('library_entry_id', $entry->id)->count())->toBe(0)
            ->and(PickFeedback::where('library_entry_id', $other->id)->count())->toBe(1);
    });

    it('keeps existing runs and marks the feedback as changed', function () {
        $this->travelTo(CarbonImmutable::parse('2026-11-12 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        RecommendationRun::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('picks.feedback.destroy', $entry));

        expect(RecommendationRun::where('user_id', $user->id)->count())->toBe(1)
            ->and($user->fresh()->feedback_changed_at?->toIso8601String())->toBe(now()->toIso8601String());
    });

    it('flashes its toast and redirects back', function () {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        $from = route('library', ['status' => 'hidden']);

        $this->actingAs($user)->from($from)->delete(route('picks.feedback.destroy', $entry))
            ->assertRedirect($from)
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Back in your suggestions.']);
    });

    it('is scoped to the user', function () {
        $user = User::factory()->create();
        $theirs = feedbackEntry($owner = User::factory()->create());
        PickFeedback::factory()->create(['user_id' => $owner->id, 'library_entry_id' => $theirs->id]);

        $this->actingAs($user)->delete(route('picks.feedback.destroy', $theirs))->assertNotFound();

        expect(PickFeedback::count())->toBe(1);
    });

    it('redirects guests to the login page', function () {
        $entry = feedbackEntry(User::factory()->create());

        $this->delete(route('picks.feedback.destroy', $entry))->assertRedirect(route('login'));
    });
});

describe('relations and the latest scope', function () {
    it('relates users, entries and feedback', function () {
        $feedback = PickFeedback::factory()->ofKind(FeedbackKind::Never)->create();

        expect($feedback->user->feedback->first()->is($feedback))->toBeTrue()
            ->and($feedback->libraryEntry->feedback->first()->is($feedback))->toBeTrue()
            ->and($feedback->libraryEntry->user_id)->toBe($feedback->user_id)
            ->and($feedback->getUpdatedAtColumn())->toBeNull();
    });

    it('keeps only the newest row per entry, ties going to the later row', function () {
        $user = User::factory()->create();
        $entry = feedbackEntry($user);
        $at = CarbonImmutable::parse('2026-11-12 10:00:00', 'UTC');
        $make = fn (FeedbackKind $kind, CarbonImmutable $when) => PickFeedback::factory()->create([
            'user_id' => $user->id, 'library_entry_id' => $entry->id, 'kind' => $kind, 'created_at' => $when,
        ]);
        $make(FeedbackKind::Never, $at->subDay());
        $make(FeedbackKind::NotTonight, $at);
        $tie = $make(FeedbackKind::Playing, $at);

        $latest = PickFeedback::query()->latestPerEntry()->get();

        expect($latest)->toHaveCount(1)
            ->and($latest->first()?->is($tie))->toBeTrue();
    });
});

it('deletes feedback with the account and leaves other users\' feedback alone', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    PickFeedback::factory()->create(['user_id' => $user->id, 'library_entry_id' => feedbackEntry($user)->id]);
    PickFeedback::factory()->create(['user_id' => $other->id, 'library_entry_id' => feedbackEntry($other)->id]);

    (new DeleteAccount)($user);

    expect(PickFeedback::count())->toBe(1)
        ->and(PickFeedback::where('user_id', $user->id)->count())->toBe(0);
});
