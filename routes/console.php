<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-import every library nightly so playtime snapshots capture changes within a day.
// onOneServer needs a shared cache lock, which Redis provides outside tests.
Schedule::command('library:queue-nightly')
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

// Demo accounts live for 24 hours (and are deleted on logout). The shared catalogue games stay.
Schedule::command('demo:prune')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Retry unmatched games that are due, and refresh stale metadata. Both jobs are globally unique and
// rate limited, so an hourly dispatch is cheap when there is nothing to do.
Schedule::command('igdb:sync')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
