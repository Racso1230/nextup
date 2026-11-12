<?php

use App\Actions\QueueLibraryImport;
use App\Console\Commands\PruneDemoAccounts;
use App\Demo\CreateDemoAccount;
use App\Enums\ImportStatus;
use App\Jobs\ImportLibrary;
use App\Jobs\RefreshFriendActivity;
use App\Models\FriendActivity;
use App\Models\Game;
use App\Models\LibraryEntry;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

describe('starting the demo', function () {
    it('creates a demo account, signs in and goes to Play next', function () {
        $this->post(route('demo.start'))->assertRedirect(route('play-next'));

        $user = User::where('is_demo', true)->sole();

        $this->assertAuthenticatedAs($user);
    });

    it('shows the demo library on Play next straight away', function () {
        $this->post(route('demo.start'));

        $this->get(route('play-next'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PlayNext')
                ->where('state', 'ready')
                ->has('picks', 10)
                ->where('picks.0.rank', 1)
                ->has('picks.0.signals'));
    });

    it('shares is_demo with every page', function () {
        $this->post(route('demo.start'));

        $this->get(route('library'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_demo', true));

        $this->actingAs(User::factory()->create());

        $this->get(route('library'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.is_demo', false));
    });

    it('is for guests only', function () {
        $this->actingAs(User::factory()->create());

        $this->post(route('demo.start'))->assertRedirect();

        expect(User::where('is_demo', true)->count())->toBe(0);
    });

    it('is throttled to five a minute per IP', function () {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('demo.start'))->assertRedirect(route('play-next'));
            Auth::guard('web')->logout();
        }

        $this->post(route('demo.start'))->assertTooManyRequests();

        expect(User::where('is_demo', true)->count())->toBe(5);
    });

    it('makes no external calls', function () {
        Http::fake();
        Queue::fake();

        $this->post(route('demo.start'));
        $this->get(route('play-next'));
        $this->get(route('library'));

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    });
});

describe('never reaching Steam or IGDB', function () {
    beforeEach(function () {
        Http::fake();
        $this->demo = app(CreateDemoAccount::class)();
    });

    it('does not queue an import', function () {
        Queue::fake();
        $before = $this->demo->import_status;

        app(QueueLibraryImport::class)($this->demo);

        Queue::assertNothingPushed();
        expect($this->demo->fresh()->import_status)->toBe($before);
    });

    it('does nothing if an import job runs anyway', function () {
        app()->call([new ImportLibrary($this->demo), 'handle']);

        Http::assertNothingSent();
        expect($this->demo->fresh()->import_status)->toBe(ImportStatus::Done)
            ->and(LibraryEntry::where('user_id', $this->demo->id)->count())->toBeGreaterThan(100);
    });

    it('does nothing if a friend refresh runs anyway', function () {
        $before = FriendActivity::where('user_id', $this->demo->id)->count();

        app()->call([new RefreshFriendActivity($this->demo), 'handle']);

        Http::assertNothingSent();
        expect(FriendActivity::where('user_id', $this->demo->id)->count())->toBe($before)->toBeGreaterThan(0);
    });

    it('is skipped by the nightly command, which still serves real users', function () {
        Queue::fake();
        $real = User::factory()->create(['import_status' => ImportStatus::Done]);

        $this->artisan('library:queue-nightly')
            ->expectsOutput('Queued 1 library imports.')
            ->assertSuccessful();

        Queue::assertPushed(ImportLibrary::class, 1);
        Queue::assertPushed(ImportLibrary::class, fn (ImportLibrary $job) => $job->user->is($real));
        Queue::assertPushed(RefreshFriendActivity::class, 1);
        Queue::assertPushed(RefreshFriendActivity::class, fn (RefreshFriendActivity $job) => $job->user->is($real));
        Http::assertNothingSent();
    });

    it('answers the refresh route with a toast instead of an import', function () {
        Queue::fake();

        $this->actingAs($this->demo)
            ->from(route('library'))
            ->post(route('library.import'))
            ->assertRedirect(route('library'))
            ->assertInertiaFlash('toast', [
                'type' => 'info',
                'message' => "The demo library can't be refreshed from Steam.",
            ]);

        Queue::assertNothingPushed();
        Http::assertNothingSent();
    });

    it('cannot be the target of a Steam sign-in', function () {
        Queue::fake();
        fakeSteamProvider(steamUser(['steamid' => $this->demo->steam_id]));

        $this->get(route('auth.steam.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        Queue::assertNothingPushed();
    });
});

describe('pruning', function () {
    it('deletes demo accounts older than 24 hours and nothing else', function () {
        $create = app(CreateDemoAccount::class);
        $old = $create();
        $fresh = $create();
        $realOld = User::factory()->create();

        User::whereKey([$old->id, $realOld->id])->update(['created_at' => now()->subHours(25)]);
        User::whereKey($fresh->id)->update(['created_at' => now()->subHours(23)]);

        $games = Game::count();

        $this->artisan('demo:prune')->expectsOutput('Deleted 1 demo accounts.')->assertSuccessful();

        expect(User::find($old->id))->toBeNull()
            ->and(LibraryEntry::where('user_id', $old->id)->count())->toBe(0)
            ->and(DB::table('friend_activity')->where('user_id', $old->id)->count())->toBe(0)
            ->and(User::find($fresh->id))->not->toBeNull()
            ->and(LibraryEntry::where('user_id', $fresh->id)->count())->toBeGreaterThan(100)
            ->and(User::find($realOld->id))->not->toBeNull()
            ->and(Game::count())->toBe($games);
    });

    it('removes every expired demo account in one run', function () {
        $create = app(CreateDemoAccount::class);
        $ids = [$create()->id, $create()->id, $create()->id];

        User::whereKey($ids)->update(['created_at' => now()->subDays(2)]);

        $this->artisan('demo:prune')->expectsOutput('Deleted 3 demo accounts.');

        expect(User::count())->toBe(0);
    });

    it('is scheduled hourly, without overlapping, on one server', function () {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'demo:prune'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 * * * *')
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue()
            ->and(PruneDemoAccounts::MAX_AGE_HOURS)->toBe(24);
    });
});

describe('logging out', function () {
    it('deletes a demo account immediately', function () {
        $this->post(route('demo.start'));
        $user = User::where('is_demo', true)->sole();
        $games = Game::count();

        $this->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        expect(User::find($user->id))->toBeNull()
            ->and(LibraryEntry::count())->toBe(0)
            ->and(Game::count())->toBe($games);
    });

    it('leaves a normal account alone', function () {
        $user = User::factory()->create();
        LibraryEntry::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));

        $this->assertGuest();
        expect(User::find($user->id))->not->toBeNull()
            ->and(LibraryEntry::where('user_id', $user->id)->count())->toBe(1);
    });
});
