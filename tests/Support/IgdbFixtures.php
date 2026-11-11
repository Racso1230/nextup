<?php

namespace Tests\Support;

use RuntimeException;

final class IgdbFixtures
{
    /**
     * Decoded fixture from tests/Fixtures/igdb/<name>.json.
     *
     * @return array<array-key, mixed>
     */
    public static function load(string $name): array
    {
        $json = file_get_contents(__DIR__.'/../Fixtures/igdb/'.$name.'.json');

        if ($json === false) {
            throw new RuntimeException("Missing IGDB fixture: {$name}");
        }

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return is_array($data) ? $data : throw new RuntimeException("Bad IGDB fixture: {$name}");
    }
}
