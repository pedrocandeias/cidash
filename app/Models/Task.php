<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $deadline
 * @property Priority $priority
 * @property TaskStatus $status
 * @property string|null $source_object_id
 * @property CarbonImmutable|null $completed_at
 */
#[Fillable(['title', 'description', 'assigned_to', 'deadline', 'priority', 'status', 'source_object_id'])]
class Task extends Model
{
    use IsRecord;

    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            if ($task->isDirty('status')) {
                $task->completed_at = $task->status === TaskStatus::Done ? now() : null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'priority' => Priority::class,
            'status' => TaskStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'source_object_id');
    }
}
