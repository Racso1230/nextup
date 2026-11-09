<?php

namespace App\Auth;

use Illuminate\Support\Arr;
use RuntimeException;
use SocialiteProviders\Steam\Provider;

/**
 * The upstream Steam provider, with one change: the profile lookup after a verified login uses https.
 *
 * socialiteproviders/steam 4.6 fetches GetPlayerSummaries over plain http with the API key in the
 * query string, which would send our key in clear text on every sign-in. OpenID verification is
 * untouched and still happens in the parent's user() before this method is called.
 */
class SteamProvider extends Provider
{
    public const PROFILE_URL = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=%s&steamids=%s';

    /**
     * @param  string|null  $token  The SteamID64 parsed from the verified assertion.
     * @return array<mixed>|null The player object from Steam, or null if it is missing.
     */
    protected function getUserByToken($token)
    {
        if ($token === null) {
            return null;
        }

        if (empty($this->clientSecret)) {
            throw new RuntimeException('The Steam API key has not been specified.');
        }

        $response = $this->getHttpClient()->get(
            sprintf(self::PROFILE_URL, $this->clientSecret, $token)
        );

        $contents = json_decode((string) $response->getBody(), true);

        if (! is_array($contents)) {
            return null;
        }

        $player = Arr::get($contents, 'response.players.0');

        return is_array($player) ? $player : null;
    }
}
