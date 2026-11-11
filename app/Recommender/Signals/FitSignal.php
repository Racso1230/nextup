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
        // Unknown time to beat is neutral, never zero.
        if ($candidate->ttbNormallySeconds === null) {
            return Params::FIT_UNKNOWN;
        }

        // Played to (or past) the time to beat: finishing is no longer the point, so neutral as well.
        if (self::pastTimeToBeat($candidate)) {
            return Params::FIT_UNKNOWN;
        }

        $remainingHours = $candidate->ttbNormallySeconds / 3600 - $candidate->playtimeHours();

        return 1 / (1 + $remainingHours / Params::FIT_HALF_HOURS);
    }

    public static function pastTimeToBeat(GameFacts $game): bool
    {
        return $game->ttbNormallySeconds !== null && $game->playtimeMinutes * 60 >= $game->ttbNormallySeconds;
    }
}
