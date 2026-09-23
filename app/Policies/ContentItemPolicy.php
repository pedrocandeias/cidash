<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\ContentItem;
use App\Models\User;
use App\Policies\Concerns\DeletesOwnRecords;
use App\Support\WorkspaceContext;

class ContentItemPolicy
{
    use DeletesOwnRecords;

    public function delete(User $user, ContentItem $item): bool
    {
        return $this->canDeleteRecord($user, $item->record);
    }

    /**
     * Approving content is for editors, managers and the super admin.
     */
    public function approve(User $user): bool
    {
        $workspace = app(WorkspaceContext::class)->get();

        return $workspace !== null && $user->hasRole($workspace, WorkspaceRole::Editor);
    }
}
