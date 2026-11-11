<?php

namespace App\Recommender;

/**
 * Cosine similarity of two sparse vectors keyed by tag key.
 */
final class Cosine
{
    /**
     * @param  array<string, float>  $a
     * @param  array<string, float>  $b
     */
    public static function similarity(array $a, array $b): float
    {
        $normA = self::norm($a);
        $normB = self::norm($b);

        if ($normA === 0.0 || $normB === 0.0) {
            return 0.0;
        }

        $dot = 0.0;

        foreach ($a as $key => $value) {
            if (isset($b[$key])) {
                $dot += $value * $b[$key];
            }
        }

        // Rounding can push an identical pair a hair past 1.
        return max(0.0, min(1.0, $dot / ($normA * $normB)));
    }

    /** @param array<string, float> $v */
    private static function norm(array $v): float
    {
        $sum = 0.0;

        foreach ($v as $value) {
            $sum += $value * $value;
        }

        return sqrt($sum);
    }
}
