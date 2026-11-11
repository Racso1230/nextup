<?php

namespace App\Recommender;

/**
 * Builds the per-user Profile once per run: IDF over the user's tagged library, the engagement-weighted taste
 * vector, and the dropped-games vector, each with per-game contributions for leave-one-out.
 */
final class ProfileBuilder
{
    public static function build(RecommendationContext $context): Profile
    {
        $idf = self::idf($context->library);

        $taste = [];
        $tasteContributions = [];
        $dropped = [];
        $droppedContributions = [];
        $playedCount = 0;

        foreach ($context->library as $game) {
            if (! $game->isPlayed() || $game->tags === []) {
                continue;
            }

            $vector = TagVector::for($game, $idf);

            $share = TagVector::scale($vector, Engagement::of($game));
            $taste = TagVector::add($taste, $share);
            $tasteContributions[$game->entryId] = $share;
            $playedCount++;

            if (self::isDropped($game, $context)) {
                // Unscaled by engagement: a quick drop is the signal, not the hours.
                $dropped = TagVector::add($dropped, $vector);
                $droppedContributions[$game->entryId] = $vector;
            }
        }

        return new Profile($idf, $taste, $tasteContributions, $dropped, $droppedContributions, $playedCount, $context->now);
    }

    /**
     * Played briefly (under 2 hours, or under 15% of the time to beat) and then left alone for 60+ days.
     */
    public static function isDropped(GameFacts $game, RecommendationContext $context): bool
    {
        if (! $game->isPlayed()) {
            return false;
        }

        $brief = $game->playtimeMinutes < Params::DROPPED_MAX_MINUTES
            || ($game->ttbNormallySeconds !== null
                && $game->playtimeMinutes * 60 < Params::DROPPED_MAX_TTB_SHARE * $game->ttbNormallySeconds);

        return $brief && Days::idleFor($game->lastPlayedAt, $context->now, Params::DROPPED_IDLE_DAYS);
    }

    /**
     * idf(t) = ln((1 + N) / (1 + df(t))) + 1, over the user's games that have at least one tag.
     *
     * @param  list<GameFacts>  $library
     * @return array<string, float>
     */
    private static function idf(array $library): array
    {
        $documentCount = 0;
        $df = [];

        foreach ($library as $game) {
            if ($game->tags === []) {
                continue;
            }

            $documentCount++;

            // Count each tag once per game, however it is listed.
            $keys = array_unique(array_map(fn (TagRef $tag): string => $tag->key(), $game->tags));

            foreach ($keys as $key) {
                $df[$key] = ($df[$key] ?? 0) + 1;
            }
        }

        $idf = [];

        foreach ($df as $key => $count) {
            $idf[$key] = log((1 + $documentCount) / (1 + $count)) + 1;
        }

        return $idf;
    }
}
