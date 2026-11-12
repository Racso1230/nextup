<?php

namespace App\Demo;

use Carbon\CarbonImmutable;

/**
 * The invented playtime of one demo library entry. A game with no DemoPlay is unplayed.
 */
final readonly class DemoPlay
{
    public function __construct(
        public int $minutes,
        public ?int $minutes2Weeks,
        public CarbonImmutable $lastPlayedAt,
    ) {}
}
