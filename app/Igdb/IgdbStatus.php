<?php

namespace App\Igdb;

/**
 * Whether IGDB credentials are set. Environments without keys (CI, a fresh clone) must stay healthy, so every
 * IGDB job and command checks this first and does nothing when it is false.
 */
final class IgdbStatus
{
    public function configured(): bool
    {
        $id = config('services.igdb.client_id');
        $secret = config('services.igdb.client_secret');

        return is_string($id) && $id !== '' && is_string($secret) && $secret !== '';
    }
}
