<?php

namespace App\Recommendations;

use App\Models\RecommendationRun;
use App\Models\User;
use App\Recommender\Contribution;
use App\Recommender\Explainer;
use App\Recommender\Recommender;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ranks a user's library and stores the run with its top picks.
 */
final class RunRecommendations
{
    /** Only the best picks are kept; the page shows a short list. */
    public const int MAX_PICKS = 20;

    public function __construct(
        private readonly LibraryContextFactory $contexts,
        private readonly Recommender $recommender,
        private readonly Explainer $explainer,
    ) {}

    /**
     * Returns null, and stores nothing, when the user has no candidates (empty or private library, or
     * everything unmatched or finished).
     */
    public function __invoke(User $user): ?RecommendationRun
    {
        $context = $this->contexts->build($user, CarbonImmutable::now('UTC'));
        $ranked = $this->recommender->rank($context);

        if ($ranked === []) {
            return null;
        }

        return DB::transaction(function () use ($user, $context, $ranked): RecommendationRun {
            $run = RecommendationRun::create([
                'user_id' => $user->id,
                'weights' => $this->weights($ranked[0]->contributions),
                'candidate_count' => count($ranked),
            ]);

            $rows = [];

            foreach (array_slice($ranked, 0, self::MAX_PICKS) as $index => $pick) {
                $contributions = [];

                foreach ($pick->contributions as $contribution) {
                    $contributions[$contribution->signal->value] = [
                        'value' => $contribution->value,
                        'weight' => $contribution->weight,
                        'weighted' => $contribution->weighted,
                    ];
                }

                $rows[] = [
                    'recommendation_run_id' => $run->id,
                    'library_entry_id' => $pick->game->entryId,
                    'rank' => $index + 1,
                    'score' => $pick->score,
                    'contributions' => json_encode($contributions, JSON_THROW_ON_ERROR),
                    'explanation' => $this->explainer->explain($pick, $context->now),
                ];
            }

            DB::table('recommendation_picks')->insert($rows);

            return $run->load('picks');
        });
    }

    /**
     * The weights the run used, read back from the contributions so the snapshot always matches what
     * scored the picks.
     *
     * @param  list<Contribution>  $contributions
     * @return array<string, float>
     */
    private function weights(array $contributions): array
    {
        $weights = [];

        foreach ($contributions as $contribution) {
            $weights[$contribution->signal->value] = $contribution->weight;
        }

        return $weights;
    }
}
