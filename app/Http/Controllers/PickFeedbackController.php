<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackKind;
use App\Http\Requests\StorePickFeedbackRequest;
use App\Models\LibraryEntry;
use App\Models\PickFeedback;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PickFeedbackController extends Controller
{
    /**
     * Records what the user said about a pick. The page opens Steam itself for "playing"; the server only records.
     */
    public function store(StorePickFeedbackRequest $request, int $libraryEntry): RedirectResponse
    {
        $user = $request->user() ?? abort(401);
        $entry = $this->ownedEntry($user, $libraryEntry);
        $kind = $request->kind();

        DB::transaction(function () use ($user, $entry, $kind): void {
            PickFeedback::create([
                'user_id' => $user->id,
                'library_entry_id' => $entry->id,
                'kind' => $kind,
            ]);

            $user->forceFill(['feedback_changed_at' => now()])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => match ($kind) {
            FeedbackKind::Playing => "Have fun. We'll keep it near the top for a few days.",
            FeedbackKind::NotTonight => 'Hidden for tonight.',
            FeedbackKind::Never => "We won't suggest it again. You can undo this in your library.",
        }]);

        return back();
    }

    /**
     * Forgets everything said about a game, which brings a "never suggest" back into the suggestions.
     */
    public function destroy(Request $request, int $libraryEntry): RedirectResponse
    {
        $user = $request->user() ?? abort(401);
        $entry = $this->ownedEntry($user, $libraryEntry);

        // Deleted rows can't be seen as "newer than the run", so the timestamp is what tells the cache.
        DB::transaction(function () use ($user, $entry): void {
            $user->feedback()->where('library_entry_id', $entry->id)->delete();
            $user->forceFill(['feedback_changed_at' => now()])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Back in your suggestions.']);

        return back();
    }

    /** The entry, only when it is the user's; anything else is a 404 whether or not it exists. */
    private function ownedEntry(User $user, int $id): LibraryEntry
    {
        return $user->libraryEntries()->findOrFail($id);
    }
}
