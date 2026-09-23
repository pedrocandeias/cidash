<?php

namespace App\Models;

use App\Core\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $object_id
 * @property int|null $user_id
 * @property string $body
 */
#[Fillable(['object_id', 'user_id', 'body'])]
class Comment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new WorkspaceScope('object_id'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
