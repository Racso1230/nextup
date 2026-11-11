<?php

namespace App\Recommender;

/**
 * One ranking signal. Implementations are small, pure and deterministic: same inputs, same output.
 */
interface Signal
{
    public function name(): SignalName;

    /**
     * A value in 0..1, or null when the signal can't be computed for this candidate (null contributes nothing
     * to the score and never appears in an explanation).
     */
    public function score(GameFacts $candidate, Profile $profile): ?float;
}
