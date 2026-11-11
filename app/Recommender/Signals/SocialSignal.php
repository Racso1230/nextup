<?php

namespace App\Recommender\Signals;

use App\Recommender\GameFacts;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * Friends who played the game recently. Friends arrive in Phase 4, so v1 is always null: it adds nothing to
 * any score and never appears in an explanation.
 */
final class SocialSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Social;
    }

    public function score(GameFacts $candidate, Profile $profile): ?float
    {
        return null;
    }
}
