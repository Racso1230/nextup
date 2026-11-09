<?php

namespace App\Steam;

final class SteamRateLimitedException extends SteamApiException
{
    public function __construct(string $endpoint, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct("Steam {$endpoint} was rate limited (HTTP 429)", $endpoint, 429);
    }
}
