<?php

namespace App\Recommender\Signals;

use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * Friends who played the game in the last two weeks: min(1, count / 3). Null when the friends list is private or
 * hasn't been fetched, so it adds nothing and never appears in an explanation.
 */
final class SocialSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Social;
    }

    public function score(GameFacts $candidate, Profile $profile): ?float
    {
        if ($candidate->friendsPlayedRecently === null) {
            return null;
        }

        return min(1.0, $candidate->friendsPlayedRecently / Params::SOCIAL_FULL_FRIENDS);
    }
}
