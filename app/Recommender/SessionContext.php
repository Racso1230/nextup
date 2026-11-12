<?php

namespace App\Recommender;

use InvalidArgumentException;

/**
 * What the user told the page about tonight. The default (no time, no mood, no company) is the evidence-best
 * ranking: "fits tonight" only counts once the user says how long they have (docs/decisions.md, 2026-11-12).
 */
final readonly class SessionContext
{
    /** The choices offered on the page; "all evening" is 6. Null means "any". */
    public const array HOUR_CHOICES = [1.0, 2.0, 4.0, 6.0];

    public function __construct(
        public ?float $hours = null,
        public ?Mood $mood = null,
        public ?Company $company = null,
    ) {
        if ($hours !== null && $hours <= 0) {
            throw new InvalidArgumentException('Available hours must be positive.');
        }
    }

    public static function default(): self
    {
        return new self;
    }

    /** @return array{hours: ?float, mood: ?string, company: ?string} */
    public function toArray(): array
    {
        return ['hours' => $this->hours, 'mood' => $this->mood?->value, 'company' => $this->company?->value];
    }
}
