<?php

namespace App\Enums;

enum EventStatus: string
{
    case Tentative = 'tentative';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Done = 'done';
}
