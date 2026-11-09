<?php

namespace App\Enums;

enum ImportStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Private = 'private';
    case Failed = 'failed';
}
