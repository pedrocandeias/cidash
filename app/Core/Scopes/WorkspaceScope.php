<?php

namespace App\Core\Scopes;

use App\Models\Record;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LogicException;

/**
 * Restricts queries to the current workspace (ARCHITECTURE.md §2.5).
 *
 * - With a $recordKey, the model is filtered through `objects` (e.g. a domain
 *   table whose `id` is an object id, or a table with an `object_id` column).
 * - Without one, the model has its own `workspace_id` column.
 *
 * Without a current workspace it fails loudly instead of returning every team's
 * data. Code that must cross workspaces (ingestion, Admin) removes the scope
 * explicitly with withoutGlobalScope(WorkspaceScope::class).
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
class WorkspaceScope implements Scope
{
    public function __construct(private ?string $recordKey = null) {}

    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $workspaceId = self::currentId();

        if ($this->recordKey === null) {
            $builder->where($model->qualifyColumn('workspace_id'), $workspaceId);

            return;
        }

        $builder->whereIn(
            $model->qualifyColumn($this->recordKey),
            Record::withoutGlobalScope(self::class)->select('id')->where('workspace_id', $workspaceId),
        );
    }

    public static function currentId(): int
    {
        $workspace = app(WorkspaceContext::class)->get()
            ?? throw new LogicException('No current workspace. Use the "workspace" middleware or remove WorkspaceScope explicitly.');

        return $workspace->id;
    }
}
