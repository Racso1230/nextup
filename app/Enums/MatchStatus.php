<?php

namespace App\Enums;

/**
 * Where a game is in the IGDB matching pipeline. Only Matched games are recommendation candidates.
 */
enum MatchStatus: string
{
    case Pending = 'pending';
    case Matched = 'matched';
    case Unmatched = 'unmatched';
    case Retrying = 'retrying';
}
