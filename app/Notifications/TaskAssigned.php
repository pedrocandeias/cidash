<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;

class TaskAssigned extends CidashNotification
{
    public function __construct(Task $task, ?User $assignedBy)
    {
        parent::__construct([
            'message' => 'You were assigned a task',
            'title' => $task->title,
            'by' => $assignedBy?->name,
            'workspace_id' => $task->record->workspace_id,
            'url' => route('tasks.show', $task, absolute: false),
        ]);
    }

    public function kind(): string
    {
        return 'assignments';
    }
}
