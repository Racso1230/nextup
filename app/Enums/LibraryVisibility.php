<?php

namespace App\Enums;

enum LibraryVisibility: string
{
    case Public = 'public';
    case Private = 'private';
    case Unknown = 'unknown';
}
