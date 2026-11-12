<?php

namespace App\Recommender\Signals;

use App\Enums\FeedbackKind;
use App\Recommender\Days;
use App\Recommender\GameFacts;
use App\Recommender\Params;
use App\Recommender\Profile;
use App\Recommender\Signal;
use App\Recommender\SignalName;

/**
 * "Started recently and not finished": the larger of recent hours and a recency decay.
 */
final class MomentumSignal implements Signal
{
    public function name(): SignalName
    {
        return SignalName::Momentum;
    }

    public function score(GameFacts $candidate, Profile $profile): float
    {
        // The user said "Playing it": full momentum for a few days, even before Steam or snapshots show it.
        $feedback = $profile->feedbackFor($candidate->entryId);

        if ($feedback !== null
            && $feedback->kind === FeedbackKind::Playing
            && $feedback->hoursAgo($profile->now) < Params::PLAYING_MOMENTUM_DAYS * 24) {
            return 1.0;
        }

        if (! $candidate->isPlayed()) {
            return 0.0;
        }

        // Snapshots win when they cover the last 14 days (even if they say 0); otherwise Steam's own figure.
        $recentMinutes = $candidate->recentMinutesFromSnapshots ?? $candidate->playtime2WeeksMinutes ?? 0;
        $fromHours = min(1.0, ($recentMinutes / 60) / Params::MOMENTUM_FULL_HOURS);

        $days = Days::since($candidate->lastPlayedAt, $profile->now);
        $fromRecency = $days === null ? 0.0 : exp(-$days / Params::MOMENTUM_DECAY_DAYS);

        return max($fromHours, $fromRecency);
    }
}
