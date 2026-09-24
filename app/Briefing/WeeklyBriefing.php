<?php

namespace App\Briefing;

use App\Enums\CampaignStatus;
use App\Enums\ContentStage;
use App\Enums\EventStatus;
use App\Enums\PressRequestStatus;
use App\Models\Briefing;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\PressRequest;
use App\Models\Workspace;
use Carbon\CarbonImmutable;

/**
 * The deterministic weekly briefing, generated on Mondays: the week ahead,
 * then a look back at the previous week (news, mentions, answers, published content).
 */
class WeeklyBriefing extends Builder
{
    public function generate(Workspace $workspace, ?CarbonImmutable $day = null): Briefing
    {
        $monday = ($day ?? CarbonImmutable::now())->startOfWeek();
        $sunday = $monday->endOfWeek()->startOfDay();
        $lastWeek = $monday->subWeek();

        return $this->store($workspace, 'weekly', $monday, $sunday, fn () => [
            $this->eventsAhead($monday),
            $this->pressAhead($monday),
            $this->contentAhead($monday),
            $this->campaignsAhead($monday),
            $this->news($workspace, $lastWeek),
            $this->mentions($lastWeek),
            $this->answered($lastWeek, $monday),
            $this->published($lastWeek, $monday),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventsAhead(CarbonImmutable $monday): array
    {
        $events = CalendarEvent::with('assignees:id,name')
            ->whereBetween('start_at', [$monday, $monday->addWeek()])
            ->where('status', '!=', EventStatus::Cancelled)
            ->orderBy('start_at')
            ->get();

        return $this->section('events', 'Events this week', $events->map(fn (CalendarEvent $event) => $this->item(
            $event->title,
            route('events.show', $event, absolute: false),
            $event->start_at->toIso8601String(),
            collect([$event->location, $event->assigneeNames()])->filter()->implode(' · ') ?: null,
            flag: $event->assignees->isEmpty(),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function pressAhead(CarbonImmutable $monday): array
    {
        $requests = PressRequest::with('assignees:id,name')
            ->whereIn('status', PressRequestStatus::open())
            ->whereNotNull('deadline')
            ->where('deadline', '<', $monday->addWeek())
            ->orderBy('deadline')
            ->get();

        return $this->section('press', 'Press deadlines this week', $requests->map(fn (PressRequest $request) => $this->item(
            $request->subject,
            route('press.show', $request, absolute: false),
            $request->deadline?->toIso8601String(),
            collect([$request->media_outlet, $request->assigneeNames()])->filter()->implode(' · ') ?: null,
            flag: $request->deadline !== null && $request->deadline->isPast(),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function contentAhead(CarbonImmutable $monday): array
    {
        $items = ContentItem::with('assignees:id,name')
            ->whereBetween('publish_at', [$monday, $monday->addWeek()])
            ->whereNotIn('stage', [ContentStage::Published, ContentStage::Archived])
            ->orderBy('publish_at')
            ->get();

        return $this->section('content', 'Content to publish this week', $items->map(fn (ContentItem $item) => $this->item(
            $item->title,
            route('content.show', $item, absolute: false),
            $item->publish_at?->toIso8601String(),
            $item->assigneeNames(),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignsAhead(CarbonImmutable $monday): array
    {
        $campaigns = Campaign::whereIn('status', [CampaignStatus::Planning, CampaignStatus::Active])
            ->whereBetween('start_date', [$monday, $monday->addDays(6)->endOfDay()])
            ->orderBy('start_date')
            ->get();

        return $this->section('campaigns', 'Campaigns starting this week', $campaigns->map(fn (Campaign $campaign) => $this->item(
            $campaign->name,
            route('campaigns.show', $campaign, absolute: false),
            $campaign->start_date?->toDateString(),
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function answered(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $requests = PressRequest::with('assignees:id,name')
            ->whereBetween('answered_at', [$from, $to])
            ->orderBy('answered_at')
            ->get();

        return $this->section('answered', 'Press requests answered last week', $requests->map(fn (PressRequest $request) => $this->item(
            $request->subject,
            route('press.show', $request, absolute: false),
            $request->answered_at?->toIso8601String(),
            collect([$request->media_outlet, $request->assigneeNames()])->filter()->implode(' · ') ?: null,
        ))->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function published(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $items = ContentItem::where('stage', ContentStage::Published)
            ->whereBetween('stage_changed_at', [$from, $to])
            ->orderBy('stage_changed_at')
            ->get();

        return $this->section('published', 'Content published last week', $items->map(fn (ContentItem $item) => $this->item(
            $item->title,
            route('content.show', $item, absolute: false),
            $item->stage_changed_at->toIso8601String(),
        ))->all());
    }
}
