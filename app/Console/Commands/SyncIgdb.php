<?php

namespace App\Console\Commands;

use App\Igdb\IgdbStatus;
use App\Jobs\MatchGames;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('igdb:sync')]
#[Description('Queue IGDB matching for due retries; matching then queues enrichment of new and stale games')]
class SyncIgdb extends Command
{
    public function handle(IgdbStatus $status): int
    {
        if (! $status->configured()) {
            $this->info('IGDB is not configured; skipped.');

            return self::SUCCESS;
        }

        // Globally unique: if a run is already queued or running this does nothing.
        MatchGames::dispatch();
        $this->info('Dispatched MatchGames.');

        return self::SUCCESS;
    }
}
