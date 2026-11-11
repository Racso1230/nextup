<?php

namespace App\Recommender;

use InvalidArgumentException;

/**
 * Signal weights, loaded from config/recommender.php. Every weight is a non-negative magnitude; the mismatch
 * weight is subtracted because mismatch is a penalty.
 */
final readonly class Weights
{
    /** @param array<string, float> $bySignal keyed by SignalName value */
    private function __construct(private array $bySignal) {}

    /**
     * @param  array<array-key, mixed>  $config  e.g. ['taste' => 0.35, 'momentum' => 0.2, ...]
     */
    public static function fromArray(array $config): self
    {
        $weights = [];

        foreach (SignalName::cases() as $signal) {
            $value = $config[$signal->value] ?? null;

            if (! is_int($value) && ! is_float($value)) {
                throw new InvalidArgumentException("Missing or non-numeric weight for {$signal->value}.");
            }

            if ($value < 0) {
                throw new InvalidArgumentException("Weight for {$signal->value} must not be negative.");
            }

            $weights[$signal->value] = (float) $value;
        }

        return new self($weights);
    }

    public function for(SignalName $signal): float
    {
        return $this->bySignal[$signal->value];
    }
}
