<?php

use App\Auth\SteamProvider;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

beforeEach(function () {
    // Login queues a library import. The queue is sync in tests, so keep it from calling Steam.
    Queue::fake();
});

test('the redirect route sends the user to steam', function () {
    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('redirect')
        ->once()
        ->andReturn(new RedirectResponse('https://steamcommunity.com/openid/login?openid.mode=checkid_setup'));
    Socialite::shouldReceive('driver')->with('steam')->andReturn($provider);

    $this->get(route('auth.steam'))
        ->assertRedirectContains('https://steamcommunity.com/openid/login');
});

test('the real provider builds a steam url from APP_URL', function () {
    config(['services.steam.redirect' => 'https://nextup.test/auth/steam/callback']);

    $location = $this->get(route('auth.steam'))->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://steamcommunity.com/openid/login?')
        ->and($location)->toContain(urlencode('https://nextup.test/auth/steam/callback'));
});

test('the steam driver is our https subclass', function () {
    expect(Socialite::driver('steam'))->toBeInstanceOf(SteamProvider::class);
});

test('the profile lookup after verification uses https, never plain http', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], (string) json_encode(['response' => ['players' => [['steamid' => STEAM_ID, 'personaname' => 'Gordon']]]])),
    ]));
    $stack->push(Middleware::history($history));

    $provider = new SteamProvider(request(), null, 'test-key', 'https://nextup.test/auth/steam/callback');
    $provider->setHttpClient(new Client(['handler' => $stack]));

    $player = (fn () => $this->getUserByToken(STEAM_ID))->call($provider);

    expect($player)->toMatchArray(['personaname' => 'Gordon'])
        ->and($history)->toHaveCount(1)
        ->and($history[0]['request']->getUri()->getScheme())->toBe('https')
        ->and($history[0]['request']->getUri()->getHost())->toBe('api.steampowered.com');
});

test('the callback creates a user and logs them in', function () {
    fakeSteamProvider(steamUser());

    $this->get(route('auth.steam.callback'))->assertRedirect(route('play-next'));

    $user = User::where('steam_id', STEAM_ID)->firstOrFail();
    expect($user->persona_name)->toBe('Gabe')
        ->and($user->avatar_url)->toBe('https://avatars.steamstatic.com/abc_full.jpg')
        ->and($user->profile_url)->toBe('https://steamcommunity.com/id/gabe/');
    $this->assertAuthenticatedAs($user);
});

test('the callback updates an existing user instead of duplicating them', function () {
    User::factory()->create([
        'steam_id' => STEAM_ID,
        'persona_name' => 'Old name',
        'avatar_url' => 'https://avatars.steamstatic.com/old_full.jpg',
    ]);
    fakeSteamProvider(steamUser(['personaname' => 'New name', 'avatarfull' => 'https://avatars.steamstatic.com/new_full.jpg']));

    $this->get(route('auth.steam.callback'))->assertRedirect(route('play-next'));

    expect(User::where('steam_id', STEAM_ID)->count())->toBe(1);
    $user = User::where('steam_id', STEAM_ID)->firstOrFail();
    expect($user->persona_name)->toBe('New name')
        ->and($user->avatar_url)->toBe('https://avatars.steamstatic.com/new_full.jpg');
});

test('the session id is regenerated on login', function () {
    fakeSteamProvider(steamUser());
    $this->get(route('login'));
    $before = session()->getId();

    $this->get(route('auth.steam.callback'));

    expect(session()->getId())->not->toBe($before);
});

test('a provider exception redirects to login with an error toast', function () {
    fakeSteamProvider(new RuntimeException('boom https://api.steampowered.com/?key=SECRET'));
    Log::spy();

    $this->get(route('auth.steam.callback'))
        ->assertRedirect(route('login'))
        ->assertInertiaFlash('toast', [
            'type' => 'error',
            'message' => "Steam sign-in didn't complete. Try again.",
        ]);

    $this->assertGuest();
    Log::shouldHaveReceived('warning')->withArgs(
        fn (string $message, array $context) => $message === 'Steam sign-in failed'
            && $context === ['reason' => RuntimeException::class],
    );
});

test('an id that is not 17 digits is rejected', function (string $id) {
    fakeSteamProvider(steamUser(['steamid' => $id]));

    $this->get(route('auth.steam.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(User::count())->toBe(0);
})->with(['too short' => '1234567', 'letters' => '7656119800000000a', 'too long' => '765611980000000012']);

test('authenticated users are redirected away from steam login', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('auth.steam'))->assertRedirect();
});

test('query parameters on the callback never decide who logs in', function () {
    fakeSteamProvider(steamUser());

    $this->get(route('auth.steam.callback', [
        'steamid' => '76561198999999999',
        'openid_claimed_id' => 'https://steamcommunity.com/openid/id/76561198999999999',
    ]))->assertRedirect(route('play-next'));

    expect(User::where('steam_id', '76561198999999999')->exists())->toBeFalse()
        ->and(auth()->user()?->steam_id)->toBe(STEAM_ID);
});
