<?php

namespace App\Providers;

use App\Igdb\IgdbClient;
use App\Igdb\TwitchTokenProvider;
use App\Listeners\ExtendSocialiteWithSteam;
use App\Steam\SteamClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
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

        $this->app->bind(TwitchTokenProvider::class, function ($app): TwitchTokenProvider {
            [$id, $secret] = $this->igdbCredentials();

            return new TwitchTokenProvider($id, $secret, $app->make(Factory::class), $app->make(Repository::class));
        });

        $this->app->bind(IgdbClient::class, function ($app): IgdbClient {
            [$id, $secret] = $this->igdbCredentials();

            // Unset credentials are allowed here: the client throws on first use, so the app still boots.
            return new IgdbClient($id, $secret, $app->make(TwitchTokenProvider::class), $app->make(Factory::class));
        });
    }

    /** @return array{string, string} */
    private function igdbCredentials(): array
    {
        $id = config('services.igdb.client_id');
        $secret = config('services.igdb.client_secret');

        return [is_string($id) ? $id : '', is_string($secret) ? $secret : ''];
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

        // IGDB allows 4 requests a second per client id. Stay under it, shared by every worker.
        RateLimiter::for('igdb', fn () => Limit::perSecond(3)->by('igdb'));
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
