<?php

namespace App\Http\Controllers;

use App\Actions\QueueLibraryImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LibraryImportController extends Controller
{
    public function store(Request $request, QueueLibraryImport $queueImport): RedirectResponse
    {
        $user = $request->user() ?? abort(401);

        if ($user->is_demo) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => "The demo library can't be refreshed from Steam.",
            ]);

            return back();
        }

        $queueImport($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Importing your library.',
        ]);

        return back();
    }
}
