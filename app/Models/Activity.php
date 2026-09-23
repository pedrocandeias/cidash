<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail and object history (`activity_log`).
 *
 * @property int $id
 * @property int|null $workspace_id
 * @property int|null $subject_user_id
 * @property string|null $object_id
 * @property int|null $user_id
 * @property string $action
 * @property array<string, mixed>|null $changes
 */
#[Fillable(['object_id', 'user_id', 'action', 'changes'])]
class Activity extends Model
{
    use BelongsToWorkspace;

    protected $table = 'activity_log';

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $changes
     */
    public static function log(string $action, ?Record $record = null, ?array $changes = null): self
    {
        return self::create([
            'object_id' => $record?->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * Instance-wide action (no workspace), e.g. by the super admin on a user account.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function logGlobal(string $action, ?User $subject = null, ?array $changes = null): self
    {
        // Created without model events: BelongsToWorkspace would require a current workspace.
        return self::withoutEvents(fn () => self::forceCreate([
            'workspace_id' => null,
            'user_id' => auth()->id(),
            'subject_user_id' => $subject?->id,
            'action' => $action,
            'changes' => $changes,
        ]));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
