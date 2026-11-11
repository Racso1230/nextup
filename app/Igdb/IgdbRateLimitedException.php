<?php

namespace App\Igdb;

final class IgdbRateLimitedException extends IgdbApiException
{
    public function __construct(string $endpoint, public readonly ?int $retryAfterSeconds = null)
    {
        parent::__construct("IGDB {$endpoint} was rate limited (HTTP 429)", $endpoint, 429);
    }
}
