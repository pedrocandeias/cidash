<?php

namespace App\Enums;

/**
 * Small, closed catalogue of link types between records (ARCHITECTURE.md §2.2).
 */
enum RelationType: string
{
    case RelatedTo = 'related_to';
    case Mentions = 'mentions';
    case PartOf = 'part_of';
    case OriginatedFrom = 'originated_from';
    case Covers = 'covers';
}
