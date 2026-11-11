<?php

namespace App\Igdb;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use SensitiveParameter;

/**
 * Gets and caches the Twitch app access token IGDB needs. The cache is the default store (Redis outside
 * tests), so every worker shares one token. The secret travels in the POST body, never in a URL, and
 * failures are rethrown without the original exception, like the other clients.
 */
final class TwitchTokenProvider
{
    public const CACHE_KEY = 'igdb:twitch_token';

    private const ENDPOINT = 'oauth2/token';

    private const URL = 'https://id.twitch.tv/oauth2/token';

    public function __construct(
        private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        private readonly Factory $http,
        private readonly Repository $cache,
    ) {}

    public function token(): string
    {
        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if ($this->clientId === '' || $this->clientSecret === '') {
            throw IgdbApiException::missingCredentials(self::ENDPOINT);
        }

        try {
            $response = $this->http
                ->timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->asForm()
                ->post(self::URL, [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => 'client_credentials',
                ]);
        } catch (ConnectionException) {
            // Deliberately not chained: the request body, with the secret, can show up in its details.
            throw IgdbApiException::connectionFailed(self::ENDPOINT);
        }

        if ($response->status() >= 400) {
            throw IgdbApiException::http(self::ENDPOINT, $response->status());
        }

        $token = $response->json('access_token');
        $expiresIn = $response->json('expires_in');

        if (! is_string($token) || $token === '' || ! is_int($expiresIn) || $expiresIn < 1) {
            throw IgdbApiException::unexpectedResponse(self::ENDPOINT);
        }

        $this->cache->put(self::CACHE_KEY, $token, self::ttl($expiresIn));

        return $token;
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /** Expire early so a token never dies mid-request: 5 minutes, or 10% for tokens shorter than 50 minutes. */
    public static function ttl(int $expiresIn): int
    {
        $margin = min(300, (int) floor($expiresIn * 0.1));

        return max(1, $expiresIn - $margin);
    }
}
