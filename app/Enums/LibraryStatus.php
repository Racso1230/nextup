<?php

namespace App\Enums;

/**
 * Derived from playtime, never stored. See LibraryEntry::status().
 */
enum LibraryStatus: string
{
    case Unplayed = 'unplayed';
    case Recent = 'recent';
    case Played = 'played';
}
