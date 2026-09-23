<?php

namespace App\Models;

use App\Enums\WorkspaceRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $workspace_id
 * @property int $user_id
 * @property WorkspaceRole $role
 */
class Membership extends Pivot
{
    protected $table = 'workspace_user';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
        ];
    }
}
