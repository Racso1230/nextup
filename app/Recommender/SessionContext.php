<?php

namespace App\Recommender;

use InvalidArgumentException;

/**
 * What the user told the page about tonight. The default (2 hours, no mood, no company) ranks exactly as v1 did.
 */
final readonly class SessionContext
{
    /** The choices offered on the page; "all evening" is 6. */
    public const array HOUR_CHOICES = [1.0, 2.0, 4.0, 6.0];

    public const float DEFAULT_HOURS = 2.0;

    public function __construct(
        public float $hours = self::DEFAULT_HOURS,
        public ?Mood $mood = null,
        public ?Company $company = null,
    ) {
        if ($hours <= 0) {
            throw new InvalidArgumentException('Available hours must be positive.');
        }
    }

    public static function default(): self
    {
        return new self;
    }

    /** @return array{hours: float, mood: ?string, company: ?string} */
    public function toArray(): array
    {
        return ['hours' => $this->hours, 'mood' => $this->mood?->value, 'company' => $this->company?->value];
    }
}
