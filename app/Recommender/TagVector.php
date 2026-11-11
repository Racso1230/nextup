<?php

namespace App\Recommender;

/**
 * Sparse tag vectors: one entry per tag key, valued by the tag type's weight times its IDF.
 */
final class TagVector
{
    /** Below this, a value left over from subtracting floats is treated as zero. */
    private const float EPSILON = 1e-12;

    /**
     * A game's own vector. Tags missing from the IDF table are skipped (every library game is in it).
     *
     * @param  array<string, float>  $idf
     * @return array<string, float>
     */
    public static function for(GameFacts $game, array $idf): array
    {
        $vector = [];

        foreach ($game->tags as $tag) {
            $key = $tag->key();

            if (isset($idf[$key])) {
                $vector[$key] = Params::tagTypeWeight($tag->type) * $idf[$key];
            }
        }

        return $vector;
    }

    /**
     * @param  array<string, float>  $vector
     * @return array<string, float>
     */
    public static function scale(array $vector, float $factor): array
    {
        return array_map(fn (float $value): float => $value * $factor, $vector);
    }

    /**
     * @param  array<string, float>  $into
     * @param  array<string, float>  $add
     * @return array<string, float>
     */
    public static function add(array $into, array $add): array
    {
        foreach ($add as $key => $value) {
            $into[$key] = ($into[$key] ?? 0.0) + $value;
        }

        return $into;
    }

    /**
     * Leave-one-out: remove one game's contribution, dropping entries that cancel to zero.
     *
     * @param  array<string, float>  $from
     * @param  array<string, float>  $remove
     * @return array<string, float>
     */
    public static function subtract(array $from, array $remove): array
    {
        foreach ($remove as $key => $value) {
            if (! isset($from[$key])) {
                continue;
            }

            $from[$key] -= $value;

            if (abs($from[$key]) < self::EPSILON) {
                unset($from[$key]);
            }
        }

        return $from;
    }
}
