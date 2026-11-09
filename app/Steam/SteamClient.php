<?php

namespace App\Steam;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Thin wrapper over the Steam Web API.
 *
 * Steam wants the key in the query string, so URLs are secrets. Every failure is rethrown as a
 * SteamApiException built from the endpoint name and status only. The original exception is not
 * chained, and nothing here logs. Retries and backoff live in the queued job, not here.
 */
final class SteamClient
{
    private readonly string $baseUrl;

    public function __construct(
        #[SensitiveParameter] private readonly string $key,
        private readonly Factory $http,
        string $baseUrl = 'https://api.steampowered.com',
    ) {
        if (! str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The Steam base URL must use https.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** Returns null when Steam has no such player. */
    public function playerSummary(string $steamId): ?PlayerSummary
    {
        $body = $this->get('GetPlayerSummaries', 'ISteamUser/GetPlayerSummaries/v2/', ['steamids' => $steamId]);

        $response = $body['response'] ?? null;
        $players = is_array($response) ? ($response['players'] ?? null) : null;

        if (! is_array($players)) {
            return null;
        }

        foreach ($players as $player) {
            if (is_array($player) && ($summary = PlayerSummary::fromArray($player)) !== null) {
                return $summary;
            }
        }

        return null;
    }

    public function ownedGames(string $steamId): OwnedGamesResult
    {
        $body = $this->get('GetOwnedGames', 'IPlayerService/GetOwnedGames/v1/', [
            'steamid' => $steamId,
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
        ]);

        return OwnedGamesResult::fromArray($body);
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array<array-key, mixed>
     */
    private function get(string $endpoint, string $path, array $params): array
    {
        if ($this->key === '') {
            throw SteamApiException::missingKey($endpoint);
        }

        try {
            $response = $this->http
                ->timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->get("{$this->baseUrl}/{$path}", ['key' => $this->key, 'format' => 'json'] + $params);
        } catch (ConnectionException) {
            // Deliberately not chained: its message and trace args can hold the full URL with the key.
            throw SteamApiException::connectionFailed($endpoint);
        }

        $this->guard($endpoint, $response);

        $body = $response->json();

        if (! is_array($body)) {
            throw SteamApiException::unexpectedResponse($endpoint);
        }

        return $body;
    }

    private function guard(string $endpoint, Response $response): void
    {
        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');

            throw new SteamRateLimitedException(
                $endpoint,
                ctype_digit($retryAfter) ? (int) $retryAfter : null,
            );
        }

        if ($status >= 400) {
            throw SteamApiException::http($endpoint, $status);
        }
    }
}
