<?php

namespace App\Evaluation\Baselines;

use App\Evaluation\Ranker;
use App\Recommender\Candidates;
use App\Recommender\GameFacts;
use App\Recommender\RecommendationContext;

/**
 * The "obvious" content-based answer: count how many of a candidate's tags appear on any played game. No IDF,
 * no engagement weighting. A played candidate is compared against the library without itself, so it can't
 * match its own tags.
 */
final class TagOverlapRanker implements Ranker
{
    public function name(): string
    {
        return 'tag-overlap';
    }

    public function rank(RecommendationContext $context): array
    {
        // How many played games carry each tag key.
        $playedWith = [];

        foreach ($context->library as $game) {
            if ($game->isPlayed()) {
                foreach (self::keys($game) as $key) {
                    $playedWith[$key] = ($playedWith[$key] ?? 0) + 1;
                }
            }
        }

        $scored = [];

        foreach (Candidates::select($context) as $game) {
            $own = $game->isPlayed() ? 1 : 0; // leave-one-out: discount the candidate's own contribution
            $overlap = 0;

            foreach (self::keys($game) as $key) {
                if (($playedWith[$key] ?? 0) - $own > 0) {
                    $overlap++;
                }
            }

            $scored[] = ['game' => $game, 'overlap' => $overlap];
        }

        usort($scored, fn (array $a, array $b): int => ($b['overlap'] <=> $a['overlap']) ?: Tiebreak::compare($a['game'], $b['game']));

        return array_map(fn (array $row): int => $row['game']->entryId, $scored);
    }

    /**
     * @return list<string>
     */
    private static function keys(GameFacts $game): array
    {
        return array_values(array_unique(array_map(fn ($tag): string => $tag->key(), $game->tags)));
    }
}
