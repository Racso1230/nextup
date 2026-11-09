<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Deletes a user and everything stored about them.
 *
 * Library entries go with the user (cascading foreign key). Rows in `games` stay, because they are a
 * shared catalogue of Steam apps with no personal data. A queued import for this user is discarded
 * when it runs, because the job deletes itself when its model is missing.
 */
class DeleteAccount
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user): void {
            // Database sessions (if that driver is in use) belong to the user too.
            DB::table('sessions')->where('user_id', $user->id)->delete();

            $user->delete();
        });
    }
}
