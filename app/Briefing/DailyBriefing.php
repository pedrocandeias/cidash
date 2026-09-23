<?php

namespace App\Briefing;

use App\Enums\AlertStatus;
use App\Enums\ContentStage;
use App\Enums\EventStatus;
use App\Enums\PressRequestStatus;
use App\Enums\TaskStatus;
use App\Models\Alert;
use App\Models\Briefing;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\Notice;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

/**
 * The deterministic daily briefing (no AI): the day's agenda, deadlines, open
 * alerts and what the news said since the previous briefing. Stored as a
 * snapshot, so the archive shows what was known on the day.
 */
class DailyBriefing extends Builder
{
    public function generate(Workspace $workspace, ?CarbonImmutable $day = null): Briefing
    {
        $day = ($day ?? CarbonImmutable::now())->startOfDay();

        return $this->store($workspace, 'daily', $day, $day, function () use ($workspace, $day) {
            // News since the previous briefing (so Monday covers the weekend), at most a week.
            $since = Briefing::where('kind', 'daily')->where('period_start', '<', $day)->max('generated_at');
            $since = $since !== null ? CarbonImmutable::parse($since)->max($day->subWeek()) : $day->subDay();

            return [
                $this->alerts(),
                $this->events($day),
                $this->press($day),
                $this->tasks($day),
                $this->content($day),
                $this->news($workspace, $since),
                $this->mentions($since),
                $this->notices(),
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function alerts(): array
    {
        $alerts = Alert::with('record')->where('status', AlertStatus::Open)->orderBy('due_at')->get();

        return $this->section('alerts', 'Open alerts', $alerts->map(fn (Alert $alert) => $this->item(
            $alert->title,
            route('alerts.index', absolute: false),
            $alert->due_at?->toIso8601String(),
            label: $alert->message,
            flag: $alert->severity->value === 'critical',
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function events(CarbonImmutable $day): array
    {
        $events = CalendarEvent::with('responsible:id,name')
            ->where('start_at', '<', $day->addDay())
            ->where(fn ($query) => $query->where('start_at', '>=', $day)->orWhere('end_at', '>=', $day))
            ->where('status', '!=', EventStatus::Cancelled)
            ->orderBy('start_at')
            ->get();

        return $this->section('events', 'Today', $events->map(fn (CalendarEvent $event) => $this->item(
            $event->title,
            route('events.show', $event, absolute: false),
            $event->all_day ? null : $event->start_at->toIso8601String(),
            collect([$event->location, $event->responsible?->name])->filter()->implode(' · ') ?: null,
            flag: $event->responsible_user_id === null,
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function press(CarbonImmutable $day): array
    {
        $requests = PressRequest::with('responsible:id,name')
            ->whereIn('status', PressRequestStatus::open())
            ->whereNotNull('deadline')
            ->where('deadline', '<', $day->addDays(2))
            ->orderBy('deadline')
            ->get();

        return $this->section('press', 'Press deadlines until tomorrow', $requests->map(fn (PressRequest $request) => $this->item(
            $request->subject,
            route('press.show', $request, absolute: false),
            $request->deadline?->toIso8601String(),
            collect([$request->media_outlet, $request->responsible?->name])->filter()->implode(' · ') ?: null,
            flag: $request->deadline !== null && $request->deadline->isPast(),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function tasks(CarbonImmutable $day): array
    {
        $tasks = Task::with('assignee:id,name')
            ->whereIn('status', TaskStatus::open())
            ->whereNotNull('deadline')
            ->where('deadline', '<=', $day->endOfDay())
            ->orderBy('deadline')
            ->get();

        return $this->section('tasks', 'Tasks due today or overdue', $tasks->map(fn (Task $task) => $this->item(
            $task->title,
            route('tasks.show', $task, absolute: false),
            $task->deadline?->toDateString(),
            $task->assignee?->name,
            flag: $task->deadline !== null && $task->deadline->lt($day),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function content(CarbonImmutable $day): array
    {
        $items = ContentItem::with('owner:id,name')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('publish_at', '>=', $day)->where('publish_at', '<', $day->addDay()))
                ->orWhere('stage', ContentStage::Review))
            ->whereNotIn('stage', [ContentStage::Published, ContentStage::Archived])
            ->orderByRaw('publish_at is null')
            ->orderBy('publish_at')
            ->get();

        return $this->section('content', 'Content publishing today or in review', $items->map(fn (ContentItem $item) => $this->item(
            $item->title,
            route('content.show', $item, absolute: false),
            $item->publish_at?->toIso8601String(),
            $item->owner?->name,
            label: $item->stage === ContentStage::Review ? 'In review' : null,
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function notices(): array
    {
        $notices = Notice::active()->where('pinned', true)->orderByDesc('published_at')->get();

        return $this->section('notices', 'Pinned notices', $notices->map(fn (Notice $notice) => $this->item(
            $notice->title,
            route('notices.show', $notice, absolute: false),
        ))->all());
    }
}
