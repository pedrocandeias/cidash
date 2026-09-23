<?php

namespace App\Enums;

/**
 * Shared by tasks, events, notices and press requests.
 */
enum Priority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
