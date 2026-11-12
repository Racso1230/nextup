<?php

namespace App\Console\Commands;

use App\Actions\DeleteAccount;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:prune')]
#[Description('Delete demo accounts created more than 24 hours ago')]
class PruneDemoAccounts extends Command
{
    /** How long a demo account lives. */
    public const int MAX_AGE_HOURS = 24;

    public function handle(DeleteAccount $delete): int
    {
        $deleted = 0;

        // Deleting through DeleteAccount cascades entries, snapshots, friend activity, runs and feedback.
        // The shared games and tags stay.
        User::query()
            ->where('is_demo', true)
            ->where('created_at', '<', now()->subHours(self::MAX_AGE_HOURS))
            ->lazyById(100)
            ->each(function (User $user) use ($delete, &$deleted): void {
                $delete($user);
                $deleted++;
            });

        $this->info("Deleted {$deleted} demo accounts.");

        return self::SUCCESS;
    }
}
