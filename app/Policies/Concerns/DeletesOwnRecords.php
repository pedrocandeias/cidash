<?php

namespace App\Policies\Concerns;

use App\Enums\WorkspaceRole;
use App\Models\Record;
use App\Models\User;
use App\Support\WorkspaceContext;

/**
 * Records are only reachable within the current workspace (WorkspaceScope), where
 * every member can create and edit them. Deleting someone else's record is for
 * managers (ARCHITECTURE.md §2.6).
 */
trait DeletesOwnRecords
{
    protected function canDeleteRecord(User $user, Record $record): bool
    {
        $workspace = app(WorkspaceContext::class)->get();

        return $record->created_by === $user->id
            || ($workspace !== null && $user->hasRole($workspace, WorkspaceRole::Manager));
    }
}
