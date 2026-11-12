<?php

namespace App\Http\Controllers;

use App\Demo\CreateDemoAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * "Try the demo": a throwaway account with a sample library, signed in straight away. It is deleted
     * on logout, or after 24 hours.
     */
    public function store(Request $request, CreateDemoAccount $createDemo): RedirectResponse
    {
        $user = $createDemo();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('play-next');
    }
}
