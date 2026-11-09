<?php

namespace App\Http\Controllers\Auth;

use App\Actions\QueueLibraryImport;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SteamLoginController extends Controller
{
    /**
     * Send the user to Steam's OpenID login page.
     */
    public function redirect(): RedirectResponse
    {
        /** @var RedirectResponse */
        return Socialite::driver('steam')->redirect();
    }

    /**
     * Handle Steam's return. The provider verifies the assertion with Steam
     * (`check_authentication`) before it gives us a user, so the SteamID64 is
     * only ever read from that verified result and never from the query string.
     */
    public function callback(Request $request, QueueLibraryImport $queueImport): RedirectResponse
    {
        try {
            $steamUser = Socialite::driver('steam')->user();
            $steamId = (string) $steamUser->getId();

            if (preg_match('/^\d{17}$/', $steamId) !== 1) {
                return $this->failed('invalid steam id');
            }

            // `avatarfull` is the 184px image; the provider only maps the medium one.
            $raw = $steamUser instanceof AbstractUser ? $steamUser->getRaw() : [];
            $avatar = $raw['avatarfull'] ?? $steamUser->getAvatar();
            $profileUrl = $raw['profileurl'] ?? null;

            $user = User::updateOrCreate(
                ['steam_id' => $steamId],
                [
                    'persona_name' => $steamUser->getNickname() ?: $steamId,
                    'avatar_url' => is_string($avatar) ? $avatar : null,
                    'profile_url' => is_string($profileUrl) ? $profileUrl : null,
                ],
            );
        } catch (Throwable $e) {
            // Log the class only. Exception messages can carry request URLs, and the
            // provider's profile lookup puts the Steam API key in its URL.
            return $this->failed($e::class);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->imported_at === null || $user->imported_at->lt(now()->subHours(6))) {
            $queueImport($user);
        }

        return redirect()->route('dashboard');
    }

    private function failed(string $reason): RedirectResponse
    {
        Log::warning('Steam sign-in failed', ['reason' => $reason]);

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => "Steam sign-in didn't complete. Try again.",
        ]);

        return redirect()->route('login');
    }
}
