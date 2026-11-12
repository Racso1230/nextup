<?php

namespace App\Console\Commands;

use App\Actions\QueueLibraryImport;
use App\Enums\ImportStatus;
use App\Jobs\RefreshFriendActivity;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('library:queue-nightly')]
#[Description('Queue a library import for every user whose import is not already queued or running')]
class QueueNightlyImports extends Command
{
    public function handle(QueueLibraryImport $queue): int
    {
        $queued = 0;

        // Private and failed users are included on purpose. A private library costs two Steam calls, and
        // people do flip their privacy setting, so skipping them would leave their library stale for good.
        // The 'steam' job rate limiter spaces the real calls, so there is no throttling here.
        User::query()
            ->whereNotIn('import_status', [ImportStatus::Queued, ImportStatus::Running])
            ->chunkById(100, function ($users) use ($queue, &$queued): void {
                foreach ($users as $user) {
                    $queue($user);
                    $queued++;
                }
            });

        // Friend activity is refreshed for everyone, after the imports are queued. The job is unique per user
        // and rate limited, and a private friends list costs a single call.
        User::query()->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                RefreshFriendActivity::dispatch($user);
            }
        });

        $this->info("Queued {$queued} library imports.");

        return self::SUCCESS;
    }
}
