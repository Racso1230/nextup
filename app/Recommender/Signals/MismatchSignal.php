<?php

namespace App\Recommender\Signals;

use App\Recommender\Cosine;
use App\Recommender\GameFacts;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;
use App\Recommender\TagVector;

/**
 * Similarity to games the user dropped. Subtracted from the score (SignalName::Mismatch is a penalty).
 */
final class MismatchSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Mismatch;
    }

    public function score(GameFacts $candidate, Profile $profile): float
    {
        $dropped = $profile->droppedVector;

        // A dropped game is compared with the other dropped games, not itself.
        $own = $profile->droppedContributions[$candidate->entryId] ?? null;

        if ($own !== null) {
            $dropped = TagVector::subtract($dropped, $own);
        }

        return Cosine::similarity($dropped, TagVector::for($candidate, $profile->idf));
    }
}
