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
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    /**
     * Sets who is responsible and, for tasks, who shares the work (co-responsible).
     * Someone in both lists is responsible.
     *
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $coUserIds
     * @return array<int, int> the people who were not assigned before (to notify)
     */
    public function syncAssignees(array $userIds, array $coUserIds = []): array
    {
        $roles = array_fill_keys(array_map('intval', $coUserIds), ['role' => 'co']);
        foreach (array_map('intval', $userIds) as $userId) {
            $roles[$userId] = ['role' => 'lead'];
        }

        $changes = $this->assignees()->sync($roles);

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
