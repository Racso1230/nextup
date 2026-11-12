<?php

namespace App\Recommender\Signals;

use App\Recommender\GameFacts;
use App\Recommender\Mood;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * How well a game suits the mood the user picked: the share of its mood-relevant tags that are in the chosen
 * list. Null when no mood is picked; a neutral 0.5 when the game has no mood tags at all.
 */
final class MoodSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Mood;
    }

    public function score(GameFacts $candidate, Profile $profile): ?float
    {
        $mood = $profile->session->mood;

        if ($mood === null) {
            return null;
        }

        $chosen = self::count($candidate, $mood);
        $other = self::count($candidate, $mood === Mood::Chill ? Mood::Intense : Mood::Chill);

        if ($chosen + $other === 0) {
            return Params::MOOD_UNKNOWN;
        }

        return $chosen / ($chosen + $other);
    }

    private static function count(GameFacts $candidate, Mood $mood): int
    {
        $matches = 0;

        foreach (Params::moodTags($mood) as $tag) {
            if ($candidate->hasTag($tag)) {
                $matches++;
            }
        }

        return $matches;
    }
}
