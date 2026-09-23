<?php

namespace App\Models;

use App\Core\Scopes\WorkspaceScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $object_id
 * @property int $user_id
 * @property CarbonImmutable $remind_at
 * @property CarbonImmutable|null $sent_at
 */
#[Fillable(['object_id', 'user_id', 'remind_at'])]
class Reminder extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new WorkspaceScope('object_id'));
    }

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'object_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
