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
        if (in_array($user->import_status, [ImportStatus::Queued, ImportStatus::Running], true)) {
            return;
        }

        $user->update(['import_status' => ImportStatus::Queued]);

        ImportLibrary::dispatch($user);
    }
}
