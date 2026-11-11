<?php

namespace App\Recommendations;

use App\Models\RecommendationRun;
use App\Models\User;
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
    public function __invoke(User $user): ?RecommendationRun
    {
        $latest = $user->recommendationRuns()->latest('created_at')->latest('id')->first();

        if ($latest !== null && $this->isFresh($latest, $user)) {
            return $latest->load('picks');
        }

        return ($this->run)($user);
    }

    private function isFresh(RecommendationRun $run, User $user): bool
    {
        if ($run->created_at === null) {
            return false;
        }

        $createdAt = CarbonImmutable::instance($run->created_at);

        if ($createdAt->lte(CarbonImmutable::now()->subHours(self::MAX_AGE_HOURS))) {
            return false;
        }

        return $user->imported_at === null || $createdAt->gt($user->imported_at);
    }
}
