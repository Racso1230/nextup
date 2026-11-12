<?php

namespace App\Recommender;

/** Who the user is playing with tonight. */
enum Company: string
{
    case Solo = 'solo';
    case Friends = 'friends';
}
