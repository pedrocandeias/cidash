<?php

namespace App\Notifications;

use App\Core\RecordTypes;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\PressRequest;
use App\Models\User;

/**
 * Someone was made responsible for an event, content or press request.
 */
class RecordAssigned extends CidashNotification
{
    public function __construct(ContentItem|CalendarEvent|PressRequest $record, ?User $by)
    {
        $object = $record->record;

        parent::__construct([
            'message' => 'You were made responsible',
            'title' => $object->title,
            'by' => $by?->name,
            'workspace_id' => $object->workspace_id,
            'url' => RecordTypes::url($object),
        ]);
    }

    public function kind(): string
    {
        return 'assignments';
    }
}
