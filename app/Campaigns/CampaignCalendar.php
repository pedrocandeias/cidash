<?php

namespace App\Campaigns;

use App\Enums\EventStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\CampaignTaskController;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\Task;

/**
 * Everything dated in a campaign, for its calendar: its events, when its content
 * is published and due, and when its tasks must be delivered.
 */
class CampaignCalendar
{
    /**
     * @return array<int, array{id: string, title: string, start: string, end: string|null, all_day: bool, kind: string, type: string|null, done: bool}>
     */
    public static function entries(Campaign $campaign): array
    {
        $itemIds = CampaignTaskController::itemIds($campaign);
        $entries = [];

        foreach (CalendarEvent::whereKey($itemIds)->where('status', '!=', EventStatus::Cancelled)->get() as $event) {
            $entries[] = self::entry($event->id, $event->title, $event->all_day ? $event->start_at->toDateString() : $event->start_at->toIso8601String(), $event->end_at?->toIso8601String(), $event->all_day, 'event', $event->type);
        }

        foreach (ContentItem::whereKey($itemIds)->get() as $item) {
            if ($item->publish_at !== null) {
                $entries[] = self::entry($item->id, $item->title, $item->publish_at->toIso8601String(), null, false, 'publication');
            }
            if ($item->due_at !== null) {
                $entries[] = self::entry($item->id, $item->title, $item->due_at->toDateString(), null, true, 'content_due');
            }
        }

        $taskIds = array_column(CampaignTaskController::tasksOf($campaign), 'id');
        foreach (Task::whereKey($taskIds)->whereNotNull('deadline')->where('status', '!=', TaskStatus::Cancelled)->get() as $task) {
            $entries[] = self::entry($task->id, $task->title, $task->deadline->toDateString(), null, true, 'task', done: $task->status === TaskStatus::Done);
        }

        return $entries;
    }

    /**
     * @return array{id: string, title: string, start: string, end: string|null, all_day: bool, kind: string, type: string|null, done: bool}
     */
    private static function entry(string $id, string $title, string $start, ?string $end, bool $allDay, string $kind, ?string $type = null, bool $done = false): array
    {
        return ['id' => $id, 'title' => $title, 'start' => $start, 'end' => $end, 'all_day' => $allDay, 'kind' => $kind, 'type' => $type, 'done' => $done];
    }
}
