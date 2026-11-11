<?php

namespace App\Recommender\Signals;

use App\Recommender\Cosine;
use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;
use App\Recommender\TagVector;

/**
 * Cosine between the user's taste vector (minus the candidate's own contribution) and the candidate's tag
 * vector, scaled down for libraries with fewer than 5 played games.
 */
final class TasteSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Taste;
    }

    public function score(GameFacts $candidate, Profile $profile): ?float
    {
        $taste = $profile->tasteVector;
        $played = $profile->playedCount;

        // Leave-one-out: a played game must never match itself.
        $own = $profile->tasteContributions[$candidate->entryId] ?? null;

        if ($own !== null) {
            $taste = TagVector::subtract($taste, $own);
            $played--;
        }

        if ($played <= 0) {
            return null;
        }

        $similarity = Cosine::similarity($taste, TagVector::for($candidate, $profile->idf));

        return $similarity * min(1.0, $played / Params::TASTE_MIN_PLAYED);
    }
}
