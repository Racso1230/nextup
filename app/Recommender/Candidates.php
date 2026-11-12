<?php

namespace App\Recommender;

use App\Enums\FeedbackKind;
use App\Enums\TagType;

/**
 * Which games can be recommended: matched games only, one per IGDB game, minus the likely-finished ones.
 */
final class Candidates
{
    /**
     * @return list<GameFacts>
     */
    public static function select(RecommendationContext $context): array
    {
        /** @var array<int, GameFacts> $byIgdb */
        $byIgdb = [];

        foreach ($context->library as $game) {
            if ($game->igdbId === null) {
                continue;
            }

            // Two Steam apps that are one IGDB game become one candidate: keep the one played most
            // (ties go to the lower entry id, so the result doesn't depend on input order).
            $current = $byIgdb[$game->igdbId] ?? null;

            if ($current === null
                || $game->playtimeMinutes > $current->playtimeMinutes
                || ($game->playtimeMinutes === $current->playtimeMinutes && $game->entryId < $current->entryId)) {
                $byIgdb[$game->igdbId] = $game;
            }
        }

        return array_values(array_filter(
            $byIgdb,
            fn (GameFacts $game): bool => ! self::isLikelyFinished($game, $context)
                && ! self::isHiddenByFeedback($game, $context)
                && self::suitsCompany($game, $context->session->company),
        ));
    }

    /**
     * "Never suggest" hides a game until undone; "Not tonight" hides it for 24 hours (then SnoozeSignal fades it).
     */
    public static function isHiddenByFeedback(GameFacts $game, RecommendationContext $context): bool
    {
        $feedback = $context->feedback[$game->entryId] ?? null;

        return match ($feedback?->kind) {
            FeedbackKind::Never => true,
            FeedbackKind::NotTonight => $feedback->hoursAgo($context->now) < Params::NOT_TONIGHT_HIDDEN_HOURS,
            default => false,
        };
    }

    /**
     * The solo / with-friends filter on IGDB game modes. A game with no mode data is never hidden.
     */
    public static function suitsCompany(GameFacts $game, ?Company $company): bool
    {
        if ($company === null) {
            return true;
        }

        $modes = [];

        foreach ($game->tags as $tag) {
            if ($tag->type === TagType::GameMode) {
                $modes[] = $tag->id;
            }
        }

        if ($modes === []) {
            return true;
        }

        return match ($company) {
            Company::Friends => array_intersect($modes, Params::MODES_WITH_FRIENDS) !== [],
            Company::Solo => in_array(Params::MODE_SINGLE_PLAYER, $modes, true),
        };
    }

    /**
     * Played at least the time to beat and not touched for 30+ days. Unknown time to beat never counts as
     * finished, which keeps endless multiplayer games eligible.
     */
    public static function isLikelyFinished(GameFacts $game, RecommendationContext $context): bool
    {
        if ($game->ttbNormallySeconds === null) {
            return false;
        }

        return $game->playtimeMinutes * 60 >= $game->ttbNormallySeconds
            && Days::idleFor($game->lastPlayedAt, $context->now, Params::FINISHED_IDLE_DAYS);
    }
}
