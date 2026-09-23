<?php

namespace App\Enums;

/**
 * Triage of monitored items (news and mentions).
 */
enum TriageStatus: string
{
    case New = 'new';
    case Relevant = 'relevant';
    case Irrelevant = 'irrelevant';
    case Archived = 'archived';
}
