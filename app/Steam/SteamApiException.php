<?php

namespace App\Steam;

use RuntimeException;

/**
 * A Steam call failed. The message is built here from the endpoint name and status code only.
 * It never holds a URL, because Steam wants the API key in the query string.
 * Callers must not pass the original HTTP exception as `previous`: its message and trace can contain the key.
 */
class SteamApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    public static function missingKey(string $endpoint): self
    {
        return new self("Steam {$endpoint} failed: no Steam API key is configured", $endpoint);
    }

    public static function connectionFailed(string $endpoint): self
    {
        return new self("Steam {$endpoint} failed: connection failed", $endpoint);
    }

    public static function unexpectedResponse(string $endpoint): self
    {
        return new self("Steam {$endpoint} failed: unexpected response", $endpoint);
    }

    public static function http(string $endpoint, int $status): self
    {
        return new self("Steam {$endpoint} failed with HTTP {$status}", $endpoint, $status);
    }
}
