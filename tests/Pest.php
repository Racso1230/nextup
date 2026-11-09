<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\OAuth2\User as SteamUser;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run on the Laravel TestCase (which blocks stray HTTP requests) with a fresh database.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Steam login helpers
|--------------------------------------------------------------------------
|
| Shared by the login and import tests. They stand in for the Socialite provider, so no test ever
| talks to Steam's OpenID endpoint.
|
*/

const STEAM_ID = '76561198000000001';

/**
 * Fake the verified user the Steam provider hands back after check_authentication.
 *
 * @param  array<string, mixed>  $overrides
 */
function steamUser(array $overrides = []): SteamUser
{
    $raw = array_merge([
        'steamid' => STEAM_ID,
        'personaname' => 'Gabe',
        'avatarmedium' => 'https://avatars.steamstatic.com/abc_medium.jpg',
        'avatarfull' => 'https://avatars.steamstatic.com/abc_full.jpg',
        'profileurl' => 'https://steamcommunity.com/id/gabe/',
    ], $overrides);

    return (new SteamUser)->setRaw($raw)->map([
        'id' => $raw['steamid'],
        'nickname' => $raw['personaname'],
        'name' => null,
        'email' => null,
        'avatar' => $raw['avatarmedium'],
    ]);
}

/**
 * Make Socialite's steam driver return the given verified user, or throw.
 */
function fakeSteamProvider(SteamUser|Throwable $result): void
{
    $provider = Mockery::mock(Provider::class);

    if ($result instanceof Throwable) {
        $provider->shouldReceive('user')->andThrow($result);
    } else {
        $provider->shouldReceive('user')->andReturn($result);
    }

    Socialite::shouldReceive('driver')->with('steam')->andReturn($provider);
}
