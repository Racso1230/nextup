<?php

namespace App\Providers;

use App\Listeners\ExtendSocialiteWithSteam;
use App\Steam\SteamClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SteamClient::class, function ($app): SteamClient {
            $key = config('services.steam.key');

            // An unset key is allowed here: the client throws on first use, so the app still boots.
            return new SteamClient(is_string($key) ? $key : '', $app->make(Factory::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(SocialiteWasCalled::class, ExtendSocialiteWithSteam::class);

        // About one Steam request a second, shared by every user: the cap is per API key.
        RateLimiter::for('steam', fn () => Limit::perMinute(60));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
