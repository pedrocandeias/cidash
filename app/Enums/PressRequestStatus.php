<?php

namespace App\Enums;

enum PressRequestStatus: string
{
    case Received = 'received';
    case InProgress = 'in_progress';
    case AwaitingInput = 'awaiting_input';
    case Answered = 'answered';
    case Declined = 'declined';
    case Closed = 'closed';

    /**
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Received, self::InProgress, self::AwaitingInput];
    }
}
