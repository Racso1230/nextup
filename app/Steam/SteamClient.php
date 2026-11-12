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
     * The user's friends as SteamID64 strings, or null when the list is private (Steam answers 401).
     * Callers must treat these ids as personal data: aggregate them, never store or log them.
     *
     * @return list<string>|null
     */
    public function friendIds(string $steamId): ?array
    {
        $body = $this->getOrNull(
            'GetFriendList',
            'ISteamUser/GetFriendList/v1/',
            ['steamid' => $steamId, 'relationship' => 'friend'],
            privateOn: [401, 403],
        );

        $list = $body['friendslist'] ?? null;
        $friends = is_array($list) ? ($list['friends'] ?? null) : null;

        if (! is_array($friends)) {
            return null;
        }

        $ids = [];

        foreach ($friends as $friend) {
            $id = is_array($friend) ? ($friend['steamid'] ?? null) : null;

            if (is_string($id) && ctype_digit($id)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Appids the player played in the last two weeks, or null when their profile hides it (Steam then
     * returns an empty `response`, or 401/403). A public profile with no recent play returns an empty list.
     *
     * @return list<int>|null
     */
    public function recentlyPlayedAppIds(string $steamId): ?array
    {
        $body = $this->getOrNull(
            'GetRecentlyPlayedGames',
            'IPlayerService/GetRecentlyPlayedGames/v1/',
            ['steamid' => $steamId],
            privateOn: [401, 403],
        );

        $response = $body['response'] ?? null;

        if (! is_array($response) || ! array_key_exists('total_count', $response)) {
            return null;
        }

        $ids = [];

        foreach (is_array($response['games'] ?? null) ? $response['games'] : [] as $game) {
            $appId = is_array($game) ? ($game['appid'] ?? null) : null;

            if (is_int($appId) && $appId > 0) {
                $ids[] = $appId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, string|int>  $params
     * @return array<array-key, mixed>
     */
    private function get(string $endpoint, string $path, array $params): array
    {
        return $this->getOrNull($endpoint, $path, $params) ?? throw SteamApiException::unexpectedResponse($endpoint);
    }

    /**
     * @param  array<string, string|int>  $params
     * @param  list<int>  $privateOn  statuses that mean "private", answered with null instead of an exception
     * @return array<array-key, mixed>|null
     */
    private function getOrNull(string $endpoint, string $path, array $params, array $privateOn = []): ?array
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

        if (in_array($response->status(), $privateOn, true)) {
            return null;
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
