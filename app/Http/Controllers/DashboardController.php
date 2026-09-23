<?php

namespace App\Http\Controllers;

use App\Core\RecordTypes;
use App\Enums\ContentStage;
use App\Enums\PressRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\Notice;
use App\Models\PressRequest;
use App\Models\Reminder;
use App\Models\Task;
use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home: "what needs my attention today?" (ARCHITECTURE.md §4).
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, WorkspaceContext $context): Response
    {
        $user = $request->user();
        $workspace = $context->get() ?? abort(403);
        $today = now()->startOfDay();

        $events = CalendarEvent::query()
            ->where('start_at', '<', $today->addDays(8))
            ->where(fn ($query) => $query->where('start_at', '>=', $today)->orWhere('end_at', '>=', $today))
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_at')
            ->limit(15)
            ->get();

        $myTasks = Task::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status', TaskStatus::open())
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->limit(8)
            ->get();

        $press = PressRequest::query()
            ->with('responsible:id,name')
            ->whereIn('status', PressRequestStatus::open())
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->limit(6)
            ->get();

        $contentByStage = ContentItem::query()
            ->whereNotIn('stage', [ContentStage::Published, ContentStage::Archived])
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $inReview = ContentItem::where('stage', ContentStage::Review)->orderBy('stage_changed_at')->get();

        $notices = Notice::active()->orderByDesc('pinned')->orderByDesc('published_at')->limit(5)->get();

        $reminders = Reminder::with('record')
            ->where('user_id', $user->id)
            ->whereNull('sent_at')
            ->where('remind_at', '<', $today->addDays(8))
            ->orderBy('remind_at')
            ->limit(6)
            ->get();

        $isEditor = $user->hasRole($workspace, WorkspaceRole::Editor);
        $isManager = $user->hasRole($workspace, WorkspaceRole::Manager);

        return Inertia::render('dashboard', [
            'counters' => [
                'events_today' => $events->filter(fn (CalendarEvent $event) => $event->start_at->isToday() || ($event->start_at->lt($today) && $event->end_at?->gte($today)))->count(),
                'my_tasks' => Task::where('assigned_to', $user->id)->whereIn('status', TaskStatus::open())->count(),
                'press_48h' => PressRequest::whereIn('status', PressRequestStatus::open())->whereNotNull('deadline')->where('deadline', '<', now()->addHours(48))->count(),
                'in_review' => $inReview->count(),
            ],
            'events' => $events->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'start_at' => $event->start_at->toIso8601String(),
                'all_day' => $event->all_day,
                'type' => $event->type->value,
            ]),
            'tasks' => $myTasks->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'deadline' => $task->deadline?->toDateString(),
                'priority' => $task->priority->value,
            ]),
            'press' => $press->map(fn (PressRequest $request) => [
                'id' => $request->id,
                'subject' => $request->subject,
                'media_outlet' => $request->media_outlet,
                'deadline' => $request->deadline?->toIso8601String(),
                'status' => $request->status->value,
                'responsible' => $request->responsible?->name,
            ]),
            'content' => [
                'stages' => collect(ContentStage::cases())
                    ->reject(fn (ContentStage $stage) => in_array($stage, [ContentStage::Published, ContentStage::Archived], true))
                    ->map(fn (ContentStage $stage) => ['stage' => $stage->value, 'count' => (int) ($contentByStage[$stage->value] ?? 0)])
                    ->values(),
                'stuck' => $inReview->filter(fn (ContentItem $item) => $item->stage_changed_at->lt(now()->subDays(3)))
                    ->map(fn (ContentItem $item) => ['id' => $item->id, 'title' => $item->title, 'since' => $item->stage_changed_at->toIso8601String()])
                    ->values(),
            ],
            'notices' => $notices->map(fn (Notice $notice) => ['id' => $notice->id, 'title' => $notice->title, 'pinned' => $notice->pinned]),
            'reminders' => $reminders->filter(fn (Reminder $reminder) => $reminder->record !== null)->map(fn (Reminder $reminder) => [
                'id' => $reminder->id,
                'remind_at' => $reminder->remind_at->toIso8601String(),
                'record' => RecordTypes::summary($reminder->record),
            ])->values(),
            // Editors see what awaits their approval; managers also see overdue tasks per person.
            'approvals' => $isEditor
                ? $inReview->map(fn (ContentItem $item) => ['id' => $item->id, 'title' => $item->title])->values()
                : null,
            'overdue' => $isManager
                ? Task::with('assignee:id,name')
                    ->whereIn('status', TaskStatus::open())
                    ->whereNotNull('deadline')
                    ->where('deadline', '<', $today)
                    ->get()
                    ->groupBy(fn (Task $task) => $task->assignee->name ?? __('Unassigned'))
                    ->map(fn ($tasks, $name) => ['name' => $name, 'count' => $tasks->count()])
                    ->values()
                : null,
        ]);
    }
}
