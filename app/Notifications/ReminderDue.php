<?php

namespace App\Notifications;

use App\Core\RecordTypes;
use App\Models\Record;
use Illuminate\Notifications\Notification;

class ReminderDue extends Notification
{
    public function __construct(private Record $record) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Reminder',
            'title' => $this->record->title,
            'by' => null,
            'workspace_id' => $this->record->workspace_id,
            'url' => RecordTypes::url($this->record),
        ];
    }
}
