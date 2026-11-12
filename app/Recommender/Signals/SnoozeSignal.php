<?php

namespace App\Recommender\Signals;

use App\Enums\FeedbackKind;
use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * "Not tonight" fading back: a penalty (subtracted) of exp(-(days - 1) / 2) once the 24-hour hide has passed.
 * Before that the game isn't a candidate at all (see Candidates).
 */
final class SnoozeSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Snooze;
    }

    public function score(GameFacts $candidate, Profile $profile): float
    {
        $feedback = $profile->feedbackFor($candidate->entryId);

        if ($feedback === null || $feedback->kind !== FeedbackKind::NotTonight) {
            return 0.0;
        }

        $days = $feedback->hoursAgo($profile->now) / 24;

        return exp(-max(0.0, $days - 1) / Params::SNOOZE_DECAY_DAYS);
    }
}
