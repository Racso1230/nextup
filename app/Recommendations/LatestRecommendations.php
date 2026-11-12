<?php

namespace App\Recommendations;

use App\Models\RecommendationRun;
use App\Models\User;
use App\Recommender\SessionContext;
use Carbon\CarbonImmutable;

/**
 * The run to show for a user: the stored one while it is still current, otherwise a fresh one.
 */
final class LatestRecommendations
{
    /** A stored run is reused for this long. */
    public const int MAX_AGE_HOURS = 6;

    public function __construct(private readonly RunRecommendations $run) {}

    /**
     * Null when the user has no candidates, in which case nothing is stored.
     */
    public function __invoke(User $user, ?SessionContext $session = null): ?RecommendationRun
    {
        $session ??= SessionContext::default();
        $latest = $user->recommendationRuns()->latest('created_at')->latest('id')->first();

        if ($latest !== null && $this->isFresh($latest, $user, $session)) {
            return $latest->load('picks');
        }

        return ($this->run)($user, $session);
    }

    /**
     * Reusable only when it was ranked for the same context, is recent, postdates the last import, and
     * no feedback has changed since (so a click always changes the next list).
     */
    private function isFresh(RecommendationRun $run, User $user, SessionContext $session): bool
    {
        if ($run->created_at === null || ! $this->sameContext($run, $session)) {
            return false;
        }

        $createdAt = CarbonImmutable::instance($run->created_at);

        if ($createdAt->lte(CarbonImmutable::now()->subHours(self::MAX_AGE_HOURS))) {
            return false;
        }

        if ($user->imported_at !== null && $createdAt->lte($user->imported_at)) {
            return false;
        }

        // Strictly newer: a click in the same second as the run may not have been part of it.
        return $user->feedback_changed_at === null || $createdAt->gt($user->feedback_changed_at);
    }

    private function sameContext(RecommendationRun $run, SessionContext $session): bool
    {
        // Loose comparison on purpose: a stored 2.0 may come back from JSON as the integer 2.
        return $run->context !== null && $run->context == $session->toArray();
    }
}
