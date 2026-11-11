<?php

namespace App\Igdb;

use RuntimeException;

/**
 * An IGDB or Twitch call failed. The message is built here from the endpoint name and status code only.
 * Callers must not pass the original HTTP exception as `previous`: its message and trace args can contain
 * the client secret (Twitch token request) or the bearer token.
 */
class IgdbApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $endpoint,
        public readonly ?int $status = null,
    ) {
        parent::__construct($message, $status ?? 0);
    }

    public static function missingCredentials(string $endpoint): self
    {
        return new self("IGDB {$endpoint} failed: IGDB credentials are not configured", $endpoint);
    }

    public static function connectionFailed(string $endpoint): self
    {
        return new self("IGDB {$endpoint} failed: connection failed", $endpoint);
    }

    public static function unexpectedResponse(string $endpoint): self
    {
        return new self("IGDB {$endpoint} failed: unexpected response", $endpoint);
    }

    public static function tooManyPages(string $endpoint): self
    {
        return new self("IGDB {$endpoint} failed: too many result pages", $endpoint);
    }

    public static function http(string $endpoint, int $status): self
    {
        return new self("IGDB {$endpoint} failed with HTTP {$status}", $endpoint, $status);
    }
}
