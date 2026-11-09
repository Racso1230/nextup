<?php

namespace App\Igdb;

use InvalidArgumentException;

/**
 * Builds the few Apicalypse fragments we need. Queries are only ever assembled from positive integers, so
 * nothing from Steam, IGDB or a user can reach the query text: anything else is rejected, not escaped.
 */
final class Apicalypse
{
    /**
     * `(1,2,3)` for numeric fields such as `id` and `game_id`.
     *
     * @param  array<array-key, mixed>  $ids
     */
    public static function idList(array $ids): string
    {
        return '('.implode(',', self::positiveInts($ids)).')';
    }

    /**
     * `("1","2")` for string fields such as `external_games.uid`, which holds the Steam appid as text.
     *
     * @param  array<array-key, mixed>  $ids
     */
    public static function quotedIdList(array $ids): string
    {
        return '('.implode(',', array_map(fn (int $id): string => '"'.$id.'"', self::positiveInts($ids))).')';
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int>
     */
    public static function positiveInts(array $ids): array
    {
        if ($ids === []) {
            throw new InvalidArgumentException('An Apicalypse id list cannot be empty.');
        }

        $clean = [];

        foreach ($ids as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Apicalypse ids must be positive integers.');
            }

            $clean[] = $id;
        }

        return $clean;
    }
}
