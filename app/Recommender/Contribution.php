<?php

namespace App\Recommender;

/**
 * One signal's part in a candidate's score. `weighted` is what was actually added to the score: positive for
 * normal signals, negative for the mismatch penalty, zero when the value is null.
 */
final readonly class Contribution
{
    public function __construct(
        public SignalName $signal,
        public ?float $value,
        public float $weight,
        public float $weighted,
    ) {}

    public static function of(SignalName $signal, ?float $value, float $weight): self
    {
        $weighted = $value === null ? 0.0 : $value * $weight * ($signal->isPenalty() ? -1 : 1);

        return new self($signal, $value, $weight, $weighted);
    }
}
