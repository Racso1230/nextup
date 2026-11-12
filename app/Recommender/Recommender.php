<?php

namespace App\Recommender;

use App\Recommender\Signals\FitSignal;
use App\Recommender\Signals\FreshnessSignal;
use App\Recommender\Signals\MismatchSignal;
use App\Recommender\Signals\MomentumSignal;
use App\Recommender\Signals\MoodSignal;
use App\Recommender\Signals\SnoozeSignal;
use App\Recommender\Signals\SocialSignal;
use App\Recommender\Signals\TasteSignal;

/**
 * Ranks a user's candidates: every signal, its weighted contribution, the total, and the taste evidence the
 * explanation needs. Pure and deterministic for a given context.
 */
final class Recommender
{
    /** How many played games and shared tags an explanation names. */
    private const int EVIDENCE_ITEMS = 2;

    /** @var list<Signal> */
    private array $signals;

    /** @param list<Signal>|null $signals defaults to every signal */
    public function __construct(?array $signals = null)
    {
        $this->signals = $signals ?? [
            new TasteSignal,
            new MomentumSignal,
            new FitSignal,
            new SocialSignal,
            new FreshnessSignal,
            new MoodSignal,
            new MismatchSignal,
            new SnoozeSignal,
        ];
    }

    /**
     * @return list<Ranked> best first
     */
    public function rank(RecommendationContext $context): array
    {
        $profile = ProfileBuilder::build($context);

        $byEntry = [];

        foreach ($context->library as $game) {
            $byEntry[$game->entryId] = $game;
        }

        $ranked = [];

        foreach (Candidates::select($context) as $candidate) {
            $contributions = [];
            $score = 0.0;

            foreach ($this->signals as $signal) {
                $name = $signal->name();
                $contribution = Contribution::of($name, $signal->score($candidate, $profile), $context->weights->for($name));
                $contributions[] = $contribution;
                $score += $contribution->weighted;
            }

            [$games, $tags] = $this->evidence($candidate, $profile, $byEntry);

            $ranked[] = new Ranked($candidate, $score, $contributions, $games, $tags, $profile->feedbackFor($candidate->entryId));
        }

        // Best first; ties broken by name, then entry id, so the order never depends on input order.
        usort($ranked, fn (Ranked $a, Ranked $b): int => [$b->score, $a->game->name, $a->game->entryId]
            <=> [$a->score, $b->game->name, $b->game->entryId]);

        return $ranked;
    }

    /**
     * The played games and the shared tags that contribute most to a candidate's taste match: the overlap of
     * each played game's taste contribution with the candidate's vector, and the largest products per tag.
     *
     * @param  array<int, GameFacts>  $byEntry
     * @return array{0: list<array{name: string, hours: float}>, 1: list<string>}
     */
    private function evidence(GameFacts $candidate, Profile $profile, array $byEntry): array
    {
        $vector = TagVector::for($candidate, $profile->idf);

        $gameOverlap = [];

        foreach ($profile->tasteContributions as $entryId => $contribution) {
            if ($entryId === $candidate->entryId) {
                continue;
            }

            $overlap = 0.0;

            foreach ($vector as $key => $value) {
                $overlap += $value * ($contribution[$key] ?? 0.0);
            }

            if ($overlap > 0) {
                $gameOverlap[$entryId] = $overlap;
            }
        }

        arsort($gameOverlap);

        $games = [];

        foreach (array_slice($gameOverlap, 0, self::EVIDENCE_ITEMS, true) as $entryId => $overlap) {
            $game = $byEntry[$entryId];
            $games[] = ['name' => $game->name, 'hours' => $game->playtimeHours()];
        }

        // Shared tags: the taste vector (minus the candidate's own share) times the candidate's weight.
        $taste = TagVector::subtract($profile->tasteVector, $profile->tasteContributions[$candidate->entryId] ?? []);
        $tagProducts = [];

        foreach ($vector as $key => $value) {
            if (($taste[$key] ?? 0.0) > 0) {
                $tagProducts[$key] = $value * $taste[$key];
            }
        }

        arsort($tagProducts);

        $names = [];

        foreach ($candidate->tags as $tag) {
            $names[$tag->key()] = $tag->name;
        }

        $tags = [];

        foreach (array_keys(array_slice($tagProducts, 0, self::EVIDENCE_ITEMS, true)) as $key) {
            $tags[] = $names[$key];
        }

        return [$games, $tags];
    }
}
