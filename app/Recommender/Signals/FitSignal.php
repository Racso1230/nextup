<?php

namespace App\Recommender\Signals;

use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * v1 "fits tonight" is finishability: the fewer hours left to finish, the better. Phase 4 swaps the constant
 * for the time the user says they have.
 */
final class FitSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Fit;
    }

    public function score(GameFacts $candidate, Profile $profile): float
    {
        if ($candidate->ttbNormallySeconds === null) {
            // Unknown is neutral, never zero.
            return Params::FIT_UNKNOWN;
        }

        $remainingHours = max(0.0, $candidate->ttbNormallySeconds / 3600 - $candidate->playtimeHours());

        return 1 / (1 + $remainingHours / Params::FIT_HALF_HOURS);
    }
}
