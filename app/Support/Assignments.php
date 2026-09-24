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
     * Rules for an `assignees` array of user ids: only members of the current team.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        $workspaceId = app(WorkspaceContext::class)->get()?->id;

        return [
            'assignees' => ['sometimes', 'array', 'max:20'],
            'assignees.*' => ['integer', Rule::exists('workspace_user', 'user_id')->where('workspace_id', $workspaceId)],
        ];
    }

    /**
     * @param  array<int, int|string>  $userIds
     */
    public static function sync(Task|ContentItem|CalendarEvent|PressRequest $record, array $userIds, ?User $actor): void
    {
        $added = $record->syncAssignees($userIds);

        User::whereKey($added)
            ->when($actor, fn ($query) => $query->whereKeyNot($actor->id))
            ->get()
            ->each(fn (User $user) => $user->notify($record instanceof Task
                ? new TaskAssigned($record, $actor)
                : new RecordAssigned($record, $actor)));
    }

    /**
     * Names and ids for the pages.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public static function present(Task|ContentItem|CalendarEvent|PressRequest $record): array
    {
        return $record->assignees->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values()->all();
    }
}
