<?php

namespace App\Enums;

/**
 * Content pipeline stages, in order (ARCHITECTURE.md §2.3).
 */
enum ContentStage: string
{
    case Idea = 'idea';
    case Preparing = 'preparing';
    case Review = 'review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Moving content that has not been approved into approved, scheduled or
     * published is an approval, reserved to editors (ARCHITECTURE.md §2.6).
     */
    public static function isApproval(self $from, self $to): bool
    {
        $unapproved = [self::Idea, self::Preparing, self::Review];
        $approved = [self::Approved, self::Scheduled, self::Published];

        return in_array($from, $unapproved, true) && in_array($to, $approved, true);
    }
}
