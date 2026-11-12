<?php

namespace App\Evaluation\Baselines;

use App\Recommender\GameFacts;

/** The shared tie-break for baselines: name (case-insensitive), then entry id, so order never depends on input. */
final class Tiebreak
{
    public static function compare(GameFacts $a, GameFacts $b): int
    {
        return strcasecmp($a->name, $b->name) ?: ($a->entryId <=> $b->entryId);
    }
}
