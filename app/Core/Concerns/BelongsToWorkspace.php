<?php

namespace App\Core\Concerns;

use App\Core\Scopes\WorkspaceScope;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For models with their own workspace_id column: scoped to the current workspace,
 * and filled with it when created.
 *
 * @mixin Model
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(new WorkspaceScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('workspace_id') === null) {
                $model->setAttribute('workspace_id', WorkspaceScope::currentId());
            }
        });
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
