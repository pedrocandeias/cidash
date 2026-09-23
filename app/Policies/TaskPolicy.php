<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Task;
use App\Models\User;
use App\Support\WorkspaceContext;

/**
 * Tasks are only reachable within the current workspace (WorkspaceScope), where
 * every member can create and edit them. Deleting someone else's task is for
 * managers (ARCHITECTURE.md §2.6).
 */
class TaskPolicy
{
    public function delete(User $user, Task $task): bool
    {
        $workspace = app(WorkspaceContext::class)->get();

        return $task->record->created_by === $user->id
            || ($workspace !== null && $user->hasRole($workspace, WorkspaceRole::Manager));
    }
}
