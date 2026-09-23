<?php

namespace App\Models;

use App\Core\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $object_id
 * @property int|null $user_id
 * @property string $original_name
 * @property string $path
 * @property string|null $mime_type
 * @property int $size
 */
#[Fillable(['object_id', 'user_id', 'original_name', 'path', 'mime_type', 'size'])]
class Attachment extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new WorkspaceScope('object_id'));

        static::deleted(fn (Attachment $attachment) => Storage::disk('local')->delete($attachment->path));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
