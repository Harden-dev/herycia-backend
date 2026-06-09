<?php

namespace App\Enums;

enum QueueEntryStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case InService = 'in_service';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
