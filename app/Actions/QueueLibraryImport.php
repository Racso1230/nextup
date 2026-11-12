<?php

namespace App\Actions;

use App\Enums\ImportStatus;
use App\Jobs\ImportLibrary;
use App\Models\User;

final class QueueLibraryImport
{
    /** Marks the user as queued and dispatches the import. Does nothing when one is already waiting or running. */
    public function __invoke(User $user): void
    {
        // Demo libraries are seeded from a fixture and never talk to Steam.
        if ($user->is_demo) {
            return;
        }

        if (in_array($user->import_status, [ImportStatus::Queued, ImportStatus::Running], true)) {
            return;
        }

        $user->update(['import_status' => ImportStatus::Queued]);

        ImportLibrary::dispatch($user);
    }
}
