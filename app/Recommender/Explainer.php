<?php

namespace App\Recommender;

use App\Enums\FeedbackKind;
use App\Recommender\Signals\FitSignal;
use DateTimeImmutable;

/**
 * Turns a ranked candidate into one plain-English sentence from its two or three strongest positive signals.
 * Every number comes from real data, and nothing is claimed that the data doesn't support (an unknown time to
 * beat never produces "hours left").
 */
final class Explainer
{
    private const int MAX_REASONS = 3;

    /** Contributions smaller than this are too weak to mention. */
    private const float MIN_WEIGHTED = 0.01;

    public function explain(Ranked $ranked, DateTimeImmutable $now): string
    {
        $positives = array_filter(
            $ranked->contributions,
            fn (Contribution $c): bool => ! $c->signal->isPenalty() && $c->value !== null && $c->weighted >= self::MIN_WEIGHTED,
        );

        usort($positives, fn (Contribution $a, Contribution $b): int => $b->weighted <=> $a->weighted);

        $fragments = [];

        foreach ($positives as $contribution) {
            $fragment = $this->fragment($contribution->signal, $ranked, $now);

            if ($fragment !== null) {
                $fragments[$contribution->signal->value] = $fragment;
            }

            if (count($fragments) === self::MAX_REASONS) {
                break;
            }
        }

        // The taste reason is a "Because ..." clause, so it reads best first whatever its weight.
        if (isset($fragments[SignalName::Taste->value])) {
            $fragments = [SignalName::Taste->value => $fragments[SignalName::Taste->value]] + $fragments;
        }

        $fragments = array_values($fragments);

        if ($fragments === []) {
            return 'It is one of the better fits in your library right now.';
        }

        // "A", "A and B", "A, B, and C".
        $last = array_pop($fragments);
        $sentence = match (count($fragments)) {
            0 => $last,
            1 => $fragments[0].' and '.$last,
            default => implode(', ', $fragments).', and '.$last,
        };

        return ucfirst($sentence).'.';
    }

    private function fragment(SignalName $signal, Ranked $ranked, DateTimeImmutable $now): ?string
    {
        $game = $ranked->game;

        return match ($signal) {
            SignalName::Taste => $this->taste($ranked),
            SignalName::Momentum => $this->momentum($ranked, $now),
            SignalName::Fit => $this->fit($game),
            SignalName::Freshness => $this->freshness($game),
            SignalName::Social => $this->social($game),
            SignalName::Mood => $this->mood($ranked),
            SignalName::Mismatch, SignalName::Snooze => null,
        };
    }

    private function social(GameFacts $game): ?string
    {
        $friends = $game->friendsPlayedRecently;

        if ($friends === null || $friends < 1) {
            return null;
        }

        return ($friends === 1 ? 'a friend' : "{$friends} friends").' played it in the last two weeks';
    }

    private function mood(Ranked $ranked): ?string
    {
        // Only claim a mood fit when the game actually has the mood's tags (0.5 is the neutral "no data" value).
        $value = $ranked->contribution(SignalName::Mood)?->value;

        if ($value === null || $value <= Params::MOOD_UNKNOWN) {
            return null;
        }

        $chill = $ranked->game->tags !== [] && array_filter(
            Params::moodTags(Mood::Chill),
            fn (TagRef $tag): bool => $ranked->game->hasTag($tag),
        ) !== [];

        return $chill ? 'it suits a chill evening' : 'it suits an intense session';
    }

    private function taste(Ranked $ranked): string
    {
        if ($ranked->evidenceGames === []) {
            return 'it matches the kinds of games you play';
        }

        $hours = array_sum(array_column($ranked->evidenceGames, 'hours'));
        $names = self::join(array_column($ranked->evidenceGames, 'name'));
        $sentence = 'because you put '.self::hours($hours).' into '.$names;

        if ($ranked->evidenceTags !== []) {
            // Parentheses keep the tag list from running into the next reason, so IGDB's own bracketed
            // abbreviations ("Role-playing (RPG)") are dropped to avoid nesting them.
            $tags = array_map(
                fn (string $tag): string => mb_strtolower(trim((string) preg_replace('/\s*\([^)]*\)/', '', $tag))),
                $ranked->evidenceTags,
            );
            $sentence .= ' ('.implode(', ', $tags).')';
        }

        return $sentence;
    }

    private function momentum(Ranked $ranked, DateTimeImmutable $now): ?string
    {
        $game = $ranked->game;

        if ($ranked->feedback?->kind === FeedbackKind::Playing
            && $ranked->feedback->hoursAgo($now) < Params::PLAYING_MOMENTUM_DAYS * 24) {
            return 'you said you\'re playing it';
        }

        $recentMinutes = $game->recentMinutesFromSnapshots ?? $game->playtime2WeeksMinutes ?? 0;

        if ($recentMinutes >= 60) {
            return 'you\'ve played '.self::hours($recentMinutes / 60).' in the last two weeks';
        }

        $days = Days::since($game->lastPlayedAt, $now);

        return match (true) {
            $days === null => null,
            $days === 0 => 'you played it today',
            $days === 1 => 'you played it yesterday',
            $days <= 60 => "you played it {$days} days ago",
            default => null,
        };
    }

    private function fit(GameFacts $game): ?string
    {
        // Unknown length, or already past it: nothing true to say about finishing.
        if ($game->ttbNormallySeconds === null || FitSignal::pastTimeToBeat($game)) {
            return null;
        }

        $total = $game->ttbNormallySeconds / 3600;

        if (! $game->isPlayed()) {
            return 'it\'s about '.self::hours($total).' long';
        }

        $remaining = $total - $game->playtimeHours();

        return $remaining < 1
            ? 'it\'s less than an hour from the end'
            : 'it\'s about '.self::hours($remaining).' from the end';
    }

    private function freshness(GameFacts $game): string
    {
        return $game->isPlayed() ? 'you tried it once and never went back' : 'you\'ve never started it';
    }

    /** "140 hours", "4.5 hours", "1 hour". */
    private static function hours(float $hours): string
    {
        $rounded = $hours >= 10 ? (string) (int) round($hours) : rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');

        return $rounded.($rounded === '1' ? ' hour' : ' hours');
    }

    /**
     * "a", "a and b", "a, b and c".
     *
     * @param  list<string>  $items
     */
    private static function join(array $items): string
    {
        if (count($items) <= 1) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }
}
