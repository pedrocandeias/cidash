<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A row of the universal `objects` table (PHP reserves "Object"). Every domain
 * record (task, event, …) shares its id with one Record; `type` is the domain
 * model's morph alias, so `subject` resolves it.
 *
 * @property string $id
 * @property int $workspace_id
 * @property string $type
 * @property string $title
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $archived_at
 */
#[Fillable(['workspace_id', 'type', 'title', 'created_by', 'archived_at'])]
class Record extends Model
{
    use BelongsToWorkspace, HasUuids;

    protected $table = 'objects';

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject', 'type', 'id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'object_tag', 'object_id', 'tag_id');
    }

    /**
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'object_id');
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activity(): HasMany
    {
        return $this->hasMany(Activity::class, 'object_id');
    }
}
