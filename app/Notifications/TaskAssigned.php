<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (database channel) shown in the header bell.
 */
class TaskAssigned extends Notification
{
    public function __construct(
        private Task $task,
        private ?User $assignedBy,
    ) {}

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
            'message' => 'You were assigned a task',
            'title' => $this->task->title,
            'by' => $this->assignedBy?->name,
            'workspace_id' => $this->task->record->workspace_id,
            'url' => route('tasks.show', $this->task, absolute: false),
        ];
    }
}
