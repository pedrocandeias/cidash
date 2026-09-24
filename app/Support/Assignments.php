<?php

namespace App\Support;

use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\User;
use App\Notifications\RecordAssigned;
use App\Notifications\TaskAssigned;
use Illuminate\Validation\Rule;

/**
 * Who is responsible for a record: validation, saving, and telling the people
 * newly made responsible (never the person who did it).
 */
class Assignments
{
    /**
     * Rules for an `assignees` array of user ids (and `co_assignees` for tasks):
     * only members of the current team.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(bool $withCo = false): array
    {
        $workspaceId = app(WorkspaceContext::class)->get()?->id;
        $member = Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId);

        return [
            'assignees' => ['sometimes', 'array', 'max:20'],
            'assignees.*' => ['integer', $member],
            ...($withCo ? [
                'co_assignees' => ['sometimes', 'array', 'max:20'],
                'co_assignees.*' => ['integer', $member],
            ] : []),
        ];
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @param  array<int, int|string>  $coUserIds
     */
    public static function sync(Task|ContentItem|CalendarEvent|PressRequest $record, array $userIds, ?User $actor, array $coUserIds = []): void
    {
        $added = $record->syncAssignees($userIds, $coUserIds);

        User::whereKey($added)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->id))
            ->get()
            ->each(fn (User $user) => $user->notify($record instanceof Task
                ? new TaskAssigned($record, $actor)
                : new RecordAssigned($record, $actor)));
    }

    /**
     * Names and ids for the pages; `lead` or `co` keeps only one role (tasks).
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function present(Task|ContentItem|CalendarEvent|PressRequest $record, ?string $role = null): array
    {
        return $record->assignees
            // @phpstan-ignore property.notFound (the pivot has the role column)
            ->when($role !== null, fn ($assignees) => $assignees->filter(fn (User $user) => $user->pivot->role === $role))
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()->all();
    }
}
