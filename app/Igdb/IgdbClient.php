<?php

namespace App\Igdb;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use SensitiveParameter;

/**
 * Thin wrapper over the IGDB v4 API.
 *
 * Every failure is rethrown as an IgdbApiException built from the endpoint name and status only. The original
 * exception is not chained, and nothing here logs. Retries and backoff live in the queued job, and the
 * `igdb` rate limiter (about 3 per second) is applied there as job middleware.
 */
final class IgdbClient
{
    /** IGDB caps a response at 500 rows, so inputs are sent in chunks of this size. */
    public const MAX_PER_REQUEST = 500;

    /** Safety cap on pages per chunk, so a misbehaving API cannot make us loop forever. */
    private const MAX_PAGES = 20;

    /** `external_game_sources` id for Steam. Confirmed against live IGDB on 2026-11-09 (Portal 2: 620 to 72). */
    private const STEAM_SOURCE_ID = 1;

    private const GAME_FIELDS = 'name,slug,first_release_date,cover.image_id,similar_games,'
        .'genres.name,themes.name,game_modes.name,player_perspectives.name,keywords.name';

    private readonly string $baseUrl;

    public function __construct(
        private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        private readonly TwitchTokenProvider $tokens,
        private readonly Factory $http,
        string $baseUrl = 'https://api.igdb.com/v4',
    ) {
        if (! str_starts_with($baseUrl, 'https://')) {
            throw new InvalidArgumentException('The IGDB base URL must use https.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Maps Steam appid to IGDB game id. Appids IGDB does not know are simply absent.
     *
     * @param  array<array-key, int>  $appIds
     * @return array<int, int>
     */
    public function steamGameIds(array $appIds): array
    {
        $map = [];

        foreach ($this->chunks($appIds) as $chunk) {
            $rows = $this->postPaged('external_games', sprintf(
                'fields uid,game; where external_game_source = %d & uid = %s;',
                self::STEAM_SOURCE_ID,
                Apicalypse::quotedIdList($chunk),
            ));

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $uid = $row['uid'] ?? null;
                $game = $row['game'] ?? null;

                if (is_string($uid) && preg_match('/^[1-9]\d{0,17}$/', $uid) === 1 && is_int($game) && $game > 0) {
                    $map[(int) $uid] ??= $game;
                }
            }
        }

        return $map;
    }

    /**
     * @param  array<array-key, int>  $igdbIds
     * @return list<IgdbGame>
     */
    public function games(array $igdbIds): array
    {
        $games = [];

        foreach ($this->chunks($igdbIds) as $chunk) {
            $rows = $this->post('games', sprintf(
                'fields %s; where id = %s; limit %d;',
                self::GAME_FIELDS,
                Apicalypse::idList($chunk),
                self::MAX_PER_REQUEST,
            ));

            foreach ($rows as $row) {
                $game = is_array($row) ? IgdbGame::fromArray($row) : null;

                if ($game !== null) {
                    $games[] = $game;
                }
            }
        }

        return $games;
    }

    /**
     * Keyed by IGDB game id. Games with no usable time to beat are absent.
     *
     * @param  array<array-key, int>  $igdbIds
     * @return array<int, TimeToBeat>
     */
    public function timesToBeat(array $igdbIds): array
    {
        $times = [];

        foreach ($this->chunks($igdbIds) as $chunk) {
            $rows = $this->postPaged('game_time_to_beats', sprintf(
                'fields game_id,hastily,normally,completely; where game_id = %s;',
                Apicalypse::idList($chunk),
            ));

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $gameId = $row['game_id'] ?? null;
                $time = TimeToBeat::fromArray($row);

                if (is_int($gameId) && $gameId > 0 && $time !== null) {
                    $times[$gameId] = $time;
                }
            }
        }

        return $times;
    }

    /**
     * Validates everything before anything is sent, then splits into request-sized chunks.
     *
     * @param  array<array-key, mixed>  $ids
     * @return list<non-empty-list<int>>
     */
    private function chunks(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Throws on anything that is not a positive int, before a request is made.
        $clean = Apicalypse::positiveInts($ids);

        return array_chunk(array_values(array_unique($clean)), self::MAX_PER_REQUEST);
    }

    /**
     * For endpoints where one id can match several rows (so a full chunk may not fit in one page): reads
     * pages of 500, sorted by id so paging is stable, until a short page comes back.
     *
     * @return list<mixed>
     */
    private function postPaged(string $endpoint, string $query): array
    {
        $rows = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $batch = $this->post($endpoint, sprintf(
                '%s sort id asc; limit %d; offset %d;',
                $query,
                self::MAX_PER_REQUEST,
                $page * self::MAX_PER_REQUEST,
            ));

            foreach ($batch as $row) {
                $rows[] = $row;
            }

            if (count($batch) < self::MAX_PER_REQUEST) {
                return $rows;
            }
        }

        throw IgdbApiException::tooManyPages($endpoint);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function post(string $endpoint, string $apicalypse): array
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw IgdbApiException::missingCredentials($endpoint);
        }

        $response = $this->send($endpoint, $apicalypse);

        if ($response->status() === 401) {
            // The cached token was revoked or expired early: fetch a fresh one and try exactly once more.
            $this->tokens->forget();
            $response = $this->send($endpoint, $apicalypse);
        }

        $this->guard($endpoint, $response);

        $body = $response->json();

        if (! is_array($body)) {
            throw IgdbApiException::unexpectedResponse($endpoint);
        }

        return $body;
    }

    private function send(string $endpoint, string $apicalypse): Response
    {
        $token = $this->tokens->token();

        try {
            return $this->http
                ->timeout(10)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders(['Client-ID' => $this->clientId])
                ->withToken($token)
                ->withBody($apicalypse, 'text/plain')
                ->post("{$this->baseUrl}/{$endpoint}");
        } catch (ConnectionException) {
            // Deliberately not chained: its details can include the request headers with the token.
            throw IgdbApiException::connectionFailed($endpoint);
        }
    }

    private function guard(string $endpoint, Response $response): void
    {
        $status = $response->status();

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');

            throw new IgdbRateLimitedException(
                $endpoint,
                ctype_digit($retryAfter) ? (int) $retryAfter : null,
            );
        }

        if ($status >= 400) {
            throw IgdbApiException::http($endpoint, $status);
        }
    }
}
