<?php

namespace App\Recommender;

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
                $fragments[] = $fragment;
            }

            if (count($fragments) === self::MAX_REASONS) {
                break;
            }
        }

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
            SignalName::Momentum => $this->momentum($game, $now),
            SignalName::Fit => $this->fit($game),
            SignalName::Freshness => $this->freshness($game),
            SignalName::Social, SignalName::Mismatch => null,
        };
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
            // Parentheses keep the tag list from running into the next reason.
            $sentence .= ' ('.mb_strtolower(implode(', ', $ranked->evidenceTags)).')';
        }

        return $sentence;
    }

    private function momentum(GameFacts $game, DateTimeImmutable $now): ?string
    {
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
        if ($game->ttbNormallySeconds === null) {
            return null;
        }

        $remaining = max(0.0, $game->ttbNormallySeconds / 3600 - $game->playtimeHours());

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
