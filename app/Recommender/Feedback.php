<?php

namespace App\Recommender;

use App\Enums\FeedbackKind;
use DateTimeImmutable;

/** The latest thing a user said about one game, and when. */
final readonly class Feedback
{
    public function __construct(
        public FeedbackKind $kind,
        public DateTimeImmutable $at,
    ) {}

    /** Hours since the feedback, as a float (never negative). */
    public function hoursAgo(DateTimeImmutable $now): float
    {
        return max(0, $now->getTimestamp() - $this->at->getTimestamp()) / 3600;
    }
}
