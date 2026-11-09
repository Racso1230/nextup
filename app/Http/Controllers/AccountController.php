<?php

namespace App\Http\Controllers;

use App\Actions\DeleteAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * The word the user types to confirm deletion, so it cannot happen with one stray click.
     */
    public const CONFIRMATION = 'DELETE';

    public function show(): Response
    {
        return Inertia::render('Account', [
            'confirmation' => self::CONFIRMATION,
        ]);
    }

    public function destroy(Request $request, DeleteAccount $deleteAccount): RedirectResponse
    {
        $request->validate(
            ['confirmation' => ['required', 'string', 'in:'.self::CONFIRMATION]],
            ['confirmation.in' => 'Type '.self::CONFIRMATION.' to confirm.', 'confirmation.required' => 'Type '.self::CONFIRMATION.' to confirm.'],
        );

        $user = $request->user() ?? abort(401);

        Auth::guard('web')->logout();
        $deleteAccount($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your account and library data have been deleted.']);

        return redirect()->route('home');
    }
}
