<?php

namespace App\Briefing;

use App\Enums\AlertStatus;
use App\Enums\ContentStage;
use App\Enums\EventStatus;
use App\Enums\PressRequestStatus;
use App\Enums\TaskStatus;
use App\Enums\TriageStatus;
use App\Models\Alert;
use App\Models\Briefing;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\Mention;
use App\Models\NewsItemState;
use App\Models\Notice;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The deterministic daily briefing (no AI): the day's agenda, deadlines, open
 * alerts and what the news said since the previous briefing. Stored as a
 * snapshot, so the archive shows what was known on the day.
 */
class DailyBriefing
{
    private const ITEMS = 10;

    public function __construct(private WorkspaceContext $context) {}

    /**
     * Generates (or regenerates) the briefing of $day for $workspace.
     */
    public function generate(Workspace $workspace, ?CarbonImmutable $day = null): Briefing
    {
        $day = ($day ?? CarbonImmutable::now())->startOfDay();
        $previous = $this->context->get();
        $this->context->set($workspace);

        try {
            // News since the previous briefing (so Monday covers the weekend), at most a week.
            $since = Briefing::where('kind', 'daily')->where('period_start', '<', $day)->max('generated_at');
            $since = $since !== null ? CarbonImmutable::parse($since)->max($day->subWeek()) : $day->subDay();

            // Dates are stored as datetimes; compare with Carbon values, not date strings.
            $briefing = Briefing::where('kind', 'daily')->where('period_start', $day)->first()
                ?? new Briefing(['workspace_id' => $workspace->id, 'kind' => 'daily', 'period_start' => $day]);

            $briefing->fill(
                [
                    'period_end' => $day,
                    'generated_at' => now(),
                    'content' => [
                        $this->alerts(),
                        $this->events($day),
                        $this->press($day),
                        $this->tasks($day),
                        $this->content($day),
                        $this->news($workspace, $since),
                        $this->mentions($since),
                        $this->notices(),
                    ],
                ],
            )->save();

            return $briefing;
        } finally {
            if ($previous !== null) {
                $this->context->set($previous);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{key: string, title: string, count: int, items: array<int, array<string, mixed>>}
     */
    private function section(string $key, string $title, array $items, ?int $count = null): array
    {
        return ['key' => $key, 'title' => $title, 'count' => $count ?? count($items), 'items' => array_slice($items, 0, self::ITEMS)];
    }

    /**
     * @return array<string, mixed>
     */
    private function item(string $title, ?string $url, ?string $at = null, ?string $detail = null, ?string $label = null, bool $flag = false): array
    {
        return compact('title', 'url', 'at', 'detail', 'label', 'flag');
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
            ->where('deadline', '<=', $day->toDateString())
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
    private function news(Workspace $workspace, CarbonImmutable $since): array
    {
        $states = NewsItemState::with('newsItem')
            ->whereIn('status', [TriageStatus::New, TriageStatus::Relevant])
            ->where('created_at', '>=', $since)
            ->get();
        $priority = DB::table('workspace_sources')->where('workspace_id', $workspace->id)->where('is_priority', true)->pluck('source_id')->all();

        // One line per story; relevant stories and priority sources first, then the biggest stories.
        $lines = $states->groupBy(fn (NewsItemState $state) => $state->newsItem->story_id ?? 'item-'.$state->id)
            ->map(fn ($group) => [
                'state' => $group->first(),
                'size' => $group->count(),
                'relevant' => $group->contains(fn (NewsItemState $state) => $state->status === TriageStatus::Relevant),
                'priority' => $group->contains(fn (NewsItemState $state) => in_array($state->newsItem->source_id, $priority, true)),
            ])
            ->sortByDesc(fn (array $line) => [$line['relevant'], $line['priority'], $line['size']])
            ->values();

        return $this->section('news', 'News coverage', $lines->map(fn (array $line) => $this->item(
            $line['state']->newsItem->headline,
            route('news.show', $line['state'], absolute: false),
            ($line['state']->newsItem->published_at ?? $line['state']->newsItem->retrieved_at)->toIso8601String(),
            collect([$line['state']->newsItem->outlet, $line['size'] > 1 ? "{$line['size']}×" : null])->filter()->implode(' · '),
            label: $line['relevant'] ? 'Relevant' : null,
            flag: $line['priority'],
        ))->all(), $lines->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function mentions(CarbonImmutable $since): array
    {
        $mentions = Mention::where('created_at', '>=', $since)
            ->where('review_status', '!=', TriageStatus::Irrelevant)
            ->orderByDesc('published_at')
            ->get();

        return $this->section('mentions', 'Media mentions', $mentions->map(fn (Mention $mention) => $this->item(
            $mention->headline,
            route('mentions.show', $mention, absolute: false),
            $mention->published_at?->toIso8601String(),
            collect([$mention->outlet, $mention->matched_keyword])->filter()->implode(' · '),
            label: $mention->review_status === TriageStatus::Relevant ? 'Relevant' : null,
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
