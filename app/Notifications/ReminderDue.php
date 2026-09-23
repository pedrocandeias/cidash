<?php

namespace App\Notifications;

use App\Core\RecordTypes;
use App\Models\Record;

class ReminderDue extends CidashNotification
{
    public function __construct(Record $record)
    {
        parent::__construct([
            'message' => 'Reminder',
            'title' => $record->title,
            'by' => null,
            'workspace_id' => $record->workspace_id,
            'url' => RecordTypes::url($record),
        ]);
    }

    public function kind(): string
    {
        return 'reminders';
    }
}
