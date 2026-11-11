<?php

namespace App\Recommender\Signals;

use App\Recommender\Days;
use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * A nudge towards games the user owns but never really started. Steam gives no purchase date, so this uses
 * only what is known: never played, or tried briefly once and left for six months.
 */
final class FreshnessSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Freshness;
    }

    public function score(GameFacts $candidate, Profile $profile): float
    {
        if (! $candidate->isPlayed()) {
            return Params::FRESH_NEVER_PLAYED;
        }

        $triedOnce = $candidate->playtimeMinutes < Params::FRESH_TRIED_MINUTES
            && Days::idleFor($candidate->lastPlayedAt, $profile->now, Params::FRESH_TRIED_IDLE_DAYS);

        return $triedOnce ? Params::FRESH_TRIED_ONCE : 0.0;
    }
}
