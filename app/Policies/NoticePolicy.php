<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Notice;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;
use App\Support\WorkspaceContext;

class NoticePolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, Notice $notice): bool
    {
        return $this->canDeleteRecord($user, $notice->record);
    }

    /**
     * Pinning is for managers (ARCHITECTURE.md §2.6).
     */
    public function pin(User $user): bool
    {
        $workspace = app(WorkspaceContext::class)->get();

        return $workspace !== null && $user->hasRole($workspace, WorkspaceRole::Manager);
    }
}
