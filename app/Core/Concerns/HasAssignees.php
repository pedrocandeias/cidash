<?php

namespace App\Core\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Several people responsible for a record (tasks, content, events, press requests).
 * The record's id is its object id, so one pivot (`record_assignees`) serves every type.
 *
 * @mixin Model
 */
trait HasAssignees
{
    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'record_assignees', 'object_id', 'user_id')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    /**
     * Sets who is responsible.
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, int> the people who were not responsible before (to notify)
     */
    public function syncAssignees(array $userIds): array
    {
        $changes = $this->assignees()->sync(array_values(array_unique(array_map('intval', $userIds))));

        return array_map('intval', $changes['attached']);
    }

    /**
     * "Ana Reis, Rui Lima", or null when nobody is responsible.
     */
    public function assigneeNames(): ?string
    {
        return $this->assignees->pluck('name')->implode(', ') ?: null;
    }

    /**
     * @return array<int, int>
     */
    public function assigneeIds(): array
    {
        return $this->assignees->modelKeys();
    }

    /**
     * Records a user is responsible for.
     *
     * @param  Builder<static>  $query
     */
    public function scopeAssignedTo(Builder $query, int $userId): void
    {
        $query->whereHas('assignees', fn (Builder $query) => $query->whereKey($userId));
    }

    /**
     * Records nobody is responsible for.
     *
     * @param  Builder<static>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereDoesntHave('assignees');
    }
}
