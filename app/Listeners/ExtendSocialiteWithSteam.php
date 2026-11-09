<?php

namespace App\Listeners;

use App\Auth\SteamProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Registers our https-only subclass as the "steam" Socialite driver, in place of the package's own listener.
 */
class ExtendSocialiteWithSteam
{
    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('steam', SteamProvider::class);
    }
}
