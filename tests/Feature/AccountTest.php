<?php

use App\Actions\DeleteAccount;
use App\Jobs\ImportLibrary;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot see or delete an account', function () {
    $this->get(route('account'))->assertRedirect(route('login'));
    $this->delete(route('account.destroy'), ['confirmation' => 'DELETE'])->assertRedirect(route('login'));
});

test('the account page renders with the confirmation word', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('account'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Account')
            ->where('confirmation', 'DELETE'));
});

test('deleting requires typing the confirmation word', function (?string $typed) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('account'))
        ->delete(route('account.destroy'), ['confirmation' => $typed])
        ->assertRedirect(route('account'))
        ->assertSessionHasErrors('confirmation');

    expect(User::find($user->id))->not->toBeNull();
    $this->assertAuthenticatedAs($user);
})->with([
    'missing' => [null],
    'wrong word' => ['delete please'],
    'lowercase' => ['delete'],
]);

test('deleting removes the user and their library, keeps the shared games, and signs them out', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $game = Game::factory()->create();
    LibraryEntry::factory()->for($user)->for($game)->create();
    LibraryEntry::factory()->for($other)->for($game)->create();
    LibraryEntry::factory()->count(3)->for($user)->create();

    $this->actingAs($user)
        ->delete(route('account.destroy'), ['confirmation' => 'DELETE'])
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull()
        ->and(LibraryEntry::where('user_id', $user->id)->count())->toBe(0)
        ->and(LibraryEntry::where('user_id', $other->id)->count())->toBe(1)
        ->and(Game::find($game->id))->not->toBeNull();
});

test('deleting removes the user\'s database sessions', function () {
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'abc123',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    (new DeleteAccount)($user);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('a queued import for a deleted user is discarded, not failed', function () {
    Queue::fake();
    $user = User::factory()->create();

    expect((new ImportLibrary($user))->deleteWhenMissingModels)->toBeTrue();
});
