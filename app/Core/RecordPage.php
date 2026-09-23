<?php

namespace App\Core;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Record;
use App\Models\Reminder;
use App\Models\User;

/**
 * Data shared by every record detail page: comments, history, relations and
 * the current user's reminders.
 */
class RecordPage
{
    public function __construct(private Links $links) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Record $record, User $user): array
    {
        return [
            'recordId' => $record->id,
            'comments' => $record->comments()->with('user:id,name')->oldest()->get()
                ->map(fn (Comment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'author' => $comment->user?->name,
                    'created_at' => $comment->created_at->toIso8601String(),
                    'can_delete' => $comment->user_id === $user->id,
                ]),
            'activity' => $record->activity()->with('user:id,name')->latest('id')->get()
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'action' => $activity->action,
                    'fields' => array_keys($activity->changes ?? []),
                    'user' => $activity->user?->name,
                    'created_at' => $activity->created_at->toIso8601String(),
                ]),
            'relations' => $this->links->of($record)
                ->map(fn (array $item) => [
                    'id' => $item['link']->id,
                    'type' => $item['link']->type->value,
                    'outgoing' => $item['outgoing'],
                    'record' => RecordTypes::summary($item['other']),
                ]),
            'reminders' => Reminder::where('object_id', $record->id)
                ->where('user_id', $user->id)
                ->whereNull('sent_at')
                ->orderBy('remind_at')
                ->get()
                ->map(fn (Reminder $reminder) => [
                    'id' => $reminder->id,
                    'remind_at' => $reminder->remind_at->toIso8601String(),
                ]),
        ];
    }
}
