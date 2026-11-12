<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that scrubs secrets from a record's message, context and extra.
 *
 * It masks the configured Steam key and IGDB client secret wherever they appear, plus anything
 * that merely looks like a credential: Bearer tokens and `key=` style query-string values.
 */
final class RedactSecrets implements ProcessorInterface
{
    public const MASK = '[redacted]';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function scrubArray(array $values): array
    {
        foreach ($values as $name => $value) {
            $values[$name] = match (true) {
                is_string($value) => $this->scrubString($value),
                is_array($value) => $this->scrubArray($value),
                default => $value,
            };
        }

        return $values;
    }

    private function scrubString(string $text): string
    {
        foreach ($this->configuredSecrets() as $secret) {
            $text = str_replace($secret, self::MASK, $text);
        }

        $text = preg_replace('/Bearer\s+[A-Za-z0-9\-._~+\/]+=*/i', 'Bearer '.self::MASK, $text) ?? $text;

        return preg_replace('/\b(key|api_key|client_secret|access_token)=[^&\s"\']*/i', '$1='.self::MASK, $text) ?? $text;
    }

    /** @return list<string> */
    private function configuredSecrets(): array
    {
        $secrets = [];

        foreach (['services.steam.key', 'services.igdb.client_secret'] as $path) {
            $value = config($path);

            // Very short values would mangle ordinary text, so only real-looking secrets count.
            if (is_string($value) && strlen($value) >= 6) {
                $secrets[] = $value;
            }
        }

        return $secrets;
    }
}
