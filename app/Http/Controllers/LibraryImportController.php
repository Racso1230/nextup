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
        $queueImport($request->user() ?? abort(401));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Importing your library.',
        ]);

        return back();
    }
}
