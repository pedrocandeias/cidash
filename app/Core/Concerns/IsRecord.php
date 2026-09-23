<?php

namespace App\Core\Concerns;

use App\Core\Scopes\WorkspaceScope;
use App\Models\Activity;
use App\Models\Record;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

/**
 * For domain models (tasks, events, …) whose table shares its uuid `id` with a
 * row in `objects` (ARCHITECTURE.md §2.1). Creating the model creates its Record
 * in the current workspace; the title is kept in sync; changes are logged; and
 * queries are restricted to the current workspace.
 *
 * The model must be registered in the morph map, whose alias becomes the type.
 * Create models inside a transaction when other writes depend on them, so a
 * failed insert does not leave an orphan object.
 *
 * @mixin Model
 */
trait IsRecord
{
    public static function bootIsRecord(): void
    {
        static::addGlobalScope(new WorkspaceScope('id'));

        static::creating(function (self $model) {
            $record = Record::create([
                'type' => $model->getMorphClass(),
                'title' => $model->recordTitle(),
                'created_by' => auth()->id(),
            ]);

            $model->setAttribute($model->getKeyName(), $record->id);
        });

        static::created(function (self $model) {
            Activity::log('created', $model->record);
        });

        static::updated(function (self $model) {
            $changes = Arr::except($model->getChanges(), [$model->getUpdatedAtColumn()]);

            if ($changes === []) {
                return;
            }

            $model->record->forceFill(['title' => $model->recordTitle()])->touch();

            Activity::log('updated', $model->record, collect($changes)
                ->map(fn ($value, $key) => ['from' => $model->getOriginal($key), 'to' => $value])
                ->all());
        });

        static::deleted(function (self $model) {
            Activity::log('deleted', $model->record, ['title' => $model->recordTitle()]);

            Record::whereKey($model->getKey())->delete();
        });
    }

    public function initializeIsRecord(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class, $this->getKeyName());
    }

    /**
     * Title shown in search, relations and activity. Override when the model has no `title`.
     */
    public function recordTitle(): string
    {
        return (string) $this->getAttribute('title');
    }
}
