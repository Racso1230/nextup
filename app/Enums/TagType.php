<?php

namespace App\Enums;

/**
 * The IGDB endpoint a tag came from. Ids are only unique within a type.
 */
enum TagType: string
{
    case Genre = 'genre';
    case Theme = 'theme';
    case GameMode = 'game_mode';
    case Perspective = 'perspective';
    case Keyword = 'keyword';
}
