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

    public function score(GameFacts $candidate, Profile $profile): ?float
    {
        // Only when the user says how long they have. Leave-one-out showed that an always-on fit favours short
        // games and buried the long ones people love (HR@5 0.116 with it, 0.395 without; docs/decisions.md).
        $hours = $profile->session->hours;

        if ($hours === null) {
            return null;
        }

        // Unknown time to beat is neutral, never zero.
        if ($candidate->ttbNormallySeconds === null) {
            return Params::FIT_UNKNOWN;
        }

        // Played to (or past) the time to beat: finishing is no longer the point, so neutral as well.
        if (self::pastTimeToBeat($candidate)) {
            return Params::FIT_UNKNOWN;
        }

        $remainingHours = $candidate->ttbNormallySeconds / 3600 - $candidate->playtimeHours();

        // The half-point scales with the time the user has tonight: 2 hours gives v1's 10 hours.
        $halfPoint = Params::FIT_HALF_HOURS_PER_AVAILABLE_HOUR * $hours;

        return 1 / (1 + $remainingHours / $halfPoint);
    }

    public static function pastTimeToBeat(GameFacts $game): bool
    {
        return $game->ttbNormallySeconds !== null && $game->playtimeMinutes * 60 >= $game->ttbNormallySeconds;
    }
}
