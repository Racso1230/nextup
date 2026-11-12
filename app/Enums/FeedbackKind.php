<?php

namespace App\Enums;

/**
 * What a user said about a recommended game (docs/recommender.md, "Feedback on a pick").
 */
enum FeedbackKind: string
{
    case Playing = 'playing';
    case NotTonight = 'not_tonight';
    case Never = 'never';
}
