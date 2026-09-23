<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;

class TaskPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, Task $task): bool
    {
        return $this->canDeleteRecord($user, $task->record);
    }
}
