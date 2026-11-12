<?php

namespace App\Providers;

use App\Igdb\IgdbClient;
use App\Igdb\TwitchTokenProvider;
use App\Listeners\ExtendSocialiteWithSteam;
use App\Notifications\JobFailedAlert;
use App\Steam\SteamClient;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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

        Queue::failing($this->reportFailedJob(...));
    }

    /**
     * Log a failed job and, when configured, email a throttled alert.
     * Only names are reported: payloads and exception messages can hold user ids and secrets.
     */
    private function reportFailedJob(JobFailed $event): void
    {
        $jobName = $event->job->resolveName();
        $exceptionClass = $event->exception::class;

        Log::critical('Queue job failed', [
            'job' => $jobName,
            'queue' => $event->job->getQueue(),
            'connection' => $event->connectionName,
            'exception' => $exceptionClass,
        ]);

        $to = config('nextup.alerts.mail_to');

        if (! is_string($to) || $to === '') {
            return;
        }

        $minutes = config('nextup.alerts.throttle_minutes');
        $ttl = now()->addMinutes(is_int($minutes) ? $minutes : 15);

        // add() only succeeds for the first failure of a job class inside the window.
        if (! Cache::add('nextup:alert:job-failed:'.sha1($jobName), true, $ttl)) {
            return;
        }

        Notification::route('mail', $to)->notify(
            new JobFailedAlert($jobName, $exceptionClass, now()->toIso8601String()),
        );
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
