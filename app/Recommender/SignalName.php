<?php

namespace App\Recommender;

/**
 * The named signals that make up a recommendation score. See docs/recommender.md.
 */
enum SignalName: string
{
    case Taste = 'taste';
    case Momentum = 'momentum';
    case Fit = 'fit';
    case Social = 'social';
    case Freshness = 'freshness';
    case Mismatch = 'mismatch';

    /** Mismatch is subtracted; every other signal adds to the score. */
    public function isPenalty(): bool
    {
        return $this === self::Mismatch;
    }
}
