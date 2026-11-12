<?php

use App\Notifications\JobFailedAlert;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

function failJob(string $name = 'App\Jobs\NightlyBatch'): void
{
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn('default');
    // The payload exists on the job but must never be read.
    $job->shouldNotReceive('getRawBody');
    $job->shouldNotReceive('payload');

    Event::dispatch(new JobFailed('redis', $job, new RuntimeException('user 42 exploded')));
}

it('logs a critical entry with names only and no payload or message', function () {
    Log::shouldReceive('critical')->once()->with('Queue job failed', [
        'job' => 'App\Jobs\NightlyBatch',
        'queue' => 'default',
        'connection' => 'redis',
        'exception' => RuntimeException::class,
    ]);

    failJob();
});

it('emails the configured address with no payload', function () {
    Notification::fake();
    config(['nextup.alerts.mail_to' => 'ops@example.test']);

    failJob();

    Notification::assertSentOnDemand(JobFailedAlert::class, function (JobFailedAlert $n, array $channels, object $notifiable) {
        $body = implode(' ', $n->toMail($notifiable)->introLines);

        return $notifiable->routes['mail'] === 'ops@example.test'
            && $n->exceptionClass === RuntimeException::class
            && str_contains($body, 'App\Jobs\NightlyBatch')
            && ! str_contains($body, 'user 42');
    });
});

it('sends no email when no address is configured', function () {
    Notification::fake();
    config(['nextup.alerts.mail_to' => '']);

    failJob();

    Notification::assertNothingSent();
});

it('sends at most one email per job class per window', function () {
    Notification::fake();
    Cache::flush();
    config(['nextup.alerts.mail_to' => 'ops@example.test']);

    failJob('App\Jobs\A');
    failJob('App\Jobs\A');
    failJob('App\Jobs\A');
    failJob('App\Jobs\B');

    Notification::assertSentOnDemandTimes(JobFailedAlert::class, 2);
});

it('alerts again once the throttle window has passed', function () {
    Notification::fake();
    Cache::flush();
    config(['nextup.alerts.mail_to' => 'ops@example.test']);

    failJob();
    $this->travel(16)->minutes();
    failJob();

    Notification::assertSentOnDemandTimes(JobFailedAlert::class, 2);
});
