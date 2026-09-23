<?php

namespace App\Models;

use App\Core\Scopes\WorkspaceScope;
use App\Enums\RelationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row of `relations`: a typed link between two records of the same workspace.
 * Named Link to avoid clashing with Eloquent's Relation class.
 *
 * @property int $id
 * @property string $source_id
 * @property string $target_id
 * @property RelationType $type
 * @property string|null $note
 * @property int|null $created_by
 */
#[Fillable(['source_id', 'target_id', 'type', 'note', 'created_by'])]
class Link extends Model
{
    protected $table = 'relations';

    protected static function booted(): void
    {
        static::addGlobalScope(new WorkspaceScope('source_id'));
    }

    protected function casts(): array
    {
        return [
            'type' => RelationType::class,
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'source_id');
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'target_id');
    }
}
