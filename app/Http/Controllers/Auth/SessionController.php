<?php

namespace App\Http\Controllers\Auth;

use App\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    /**
     * Log the user out and end their session.
     */
    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // A demo account has no way back in, so it goes now instead of waiting for the hourly prune.
        if ($user?->is_demo) {
            $deleteAccount($user);
        }

        return redirect()->route('home');
    }
}
