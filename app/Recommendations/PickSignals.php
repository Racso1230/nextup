<?php

namespace App\Recommendations;

/**
 * The score bars shown on the top pick, shared by the Play next page and the landing page hero so both
 * use the same labels and the same rules for what is left out.
 */
final class PickSignals
{
    /** The signals shown as bars, in order, with their labels. The mismatch and snooze penalties are left out. */
    private const array LABELS = [
        'taste' => 'Taste match',
        'momentum' => 'Momentum',
        'fit' => 'Fits tonight',
        // Null (so left out) when no mood is chosen. The snooze penalty is never shown, like mismatch.
        'mood' => 'Mood match',
        // Null (left out) when the friends list is private; hidden at 0 like freshness.
        'social' => 'Friends',
        // Covers both "never started" (1.0) and "tried once, never went back" (0.5).
        'freshness' => 'Fresh start',
    ];

    /**
     * A 0 bar would read as a claim ("no momentum", "no friends"), so these are only shown when they apply.
     * Momentum is 0 for a game that was never played, which is exactly when it is most likely to be picked.
     */
    private const array HIDE_WHEN_ZERO = ['momentum', 'freshness', 'social'];

    /**
     * @param  array<string, float|null>  $values  signal key => value (0 to 1), null when it didn't apply
     * @return list<array{key: string, label: string, value: float}>
     */
    public static function from(array $values): array
    {
        $signals = [];

        foreach (self::LABELS as $key => $label) {
            $value = $values[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $value = max(0.0, min(1.0, $value));

            if ($value === 0.0 && in_array($key, self::HIDE_WHEN_ZERO, true)) {
                continue;
            }

            $signals[] = ['key' => $key, 'label' => $label, 'value' => $value];
        }

        return $signals;
    }
}
