<?php

namespace App\Steam;

/**
 * Narrows untrusted decoded JSON values to concrete types. Returns null instead of throwing,
 * so callers can skip malformed entries.
 */
final class Narrow
{
    /** @param  array<array-key, mixed>  $data */
    public static function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param  array<array-key, mixed>  $data */
    public static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_int($value) ? $value : null;
    }

    /** @param  array<array-key, mixed>  $data */
    public static function nonEmptyString(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        return $value === null || $value === '' ? null : $value;
    }
}
