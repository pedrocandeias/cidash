<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Core\RecordPage;
use App\Core\Tags;
use App\Enums\CampaignStatus;
use App\Enums\EventStatus;
use App\Enums\Priority;
use App\Enums\RelationType;
use App\Http\Requests\CalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\Tag;
use App\Models\User;
use App\Support\Assignments;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CalendarEventController extends Controller
{
    public function __construct(private WorkspaceContext $context, private Tags $tags) {}

    public function index(Request $request): Response
    {
        $token = $request->user()->calendar_token;

        return Inertia::render('events/index', [
            'subscriptionUrl' => $token !== null ? route('calendar.feed', $token) : null,
            'members' => $this->members(),
            'campaigns' => Campaign::whereIn('status', [CampaignStatus::Planning, CampaignStatus::Active])
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Events overlapping [start, end), in FullCalendar's format.
     */
    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['required', 'date'],
            'types' => ['sometimes', 'array'],
            'types.*' => ['string'],
            'responsible' => ['sometimes', 'nullable', 'integer'],
            'campaign' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $start = CarbonImmutable::parse($validated['start'])->setTimezone(config('app.timezone'));
        $end = CarbonImmutable::parse($validated['end'])->setTimezone(config('app.timezone'));

        $events = CalendarEvent::query()
            ->where('start_at', '<', $end)
            ->where(fn ($query) => $query
                ->where('end_at', '>=', $start)
                ->orWhere(fn ($query) => $query->whereNull('end_at')->where('start_at', '>=', $start)))
            ->when($validated['types'] ?? null, fn ($query, $types) => $query->whereIn('type', $types))
            ->when($validated['responsible'] ?? null, fn ($query, $user) => $query->assignedTo((int) $user))
            // Events that are part of the campaign (relation part_of).
            ->when($validated['campaign'] ?? null, fn ($query, $campaign) => $query->whereIn('id', Link::where('target_id', $campaign)
                ->where('type', RelationType::PartOf)->select('source_id')))
            ->orderBy('start_at')
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'allDay' => $event->all_day,
                'start' => $event->all_day ? $event->start_at->toDateString() : $event->start_at->toIso8601String(),
                // FullCalendar ends are exclusive; all-day ends are stored inclusive.
                'end' => $event->end_at === null ? null : ($event->all_day
                    ? $event->end_at->addDay()->toDateString()
                    : $event->end_at->toIso8601String()),
                'url' => route('events.show', $event, absolute: false),
                'extendedProps' => ['type' => $event->type, 'status' => $event->status->value],
            ]);

        return response()->json($events);
    }

    public function store(CalendarEventRequest $request, Links $links): RedirectResponse
    {
        $event = DB::transaction(function () use ($request, $links) {
            $event = CalendarEvent::create([
                ...$this->attributes($request),
                'priority' => $request->input('priority', Priority::Normal->value),
                'status' => $request->input('status', EventStatus::Confirmed->value),
            ]);
            $this->tags->sync($event->record, $request->input('tags', []));
            Assignments::sync($event, $request->input('assignees', []), $request->user());

            if ($request->filled('campaign_id')) {
                $links->link($event, Campaign::findOrFail((string) $request->input('campaign_id')), RelationType::PartOf);
            }

            return $event;
        });

        // From a campaign's calendar, stay on the campaign.
        return $request->filled('campaign_id') ? back() : to_route('events.show', $event);
    }

    public function show(Request $request, CalendarEvent $event, RecordPage $page): Response
    {
        $event->load(['record.tags', 'assignees:id,name']);

        return Inertia::render('events/show', [
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'type' => $event->type,
                'start_at' => $event->start_at->toIso8601String(),
                'end_at' => $event->end_at?->toIso8601String(),
                'all_day' => $event->all_day,
                'location' => $event->location,
                'organizer' => $event->organizer,
                'assignees' => Assignments::present($event),
                'priority' => $event->priority->value,
                'status' => $event->status->value,
                'notes' => $event->notes,
                'tags' => $event->record->tags->sortBy('name')->map(fn (Tag $tag) => $tag->name)->values(),
            ],
            'members' => $this->members(),
            ...$page->for($event->record, $request->user()),
            'can' => ['delete' => $request->user()->can('delete', $event)],
        ]);
    }

    public function update(CalendarEventRequest $request, CalendarEvent $event): RedirectResponse
    {
        DB::transaction(function () use ($request, $event) {
            $event->update($this->attributes($request));

            if ($request->has('tags')) {
                $this->tags->sync($event->record, $request->input('tags', []));
            }
            if ($request->has('assignees')) {
                Assignments::sync($event, $request->input('assignees', []), $request->user());
            }
        });

        return back();
    }

    public function destroy(CalendarEvent $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event deleted.')]);

        return to_route('events.index');
    }

    /**
     * Validated attributes, with all-day events stored as whole days.
     *
     * @return array<string, mixed>
     */
    private function attributes(CalendarEventRequest $request): array
    {
        $attributes = $request->safe()->except(['tags', 'assignees', 'campaign_id']);

        foreach (['start_at', 'end_at'] as $field) {
            if (! empty($attributes[$field])) {
                // Times may carry an offset (e.g. dragged in the calendar); they are stored in the app time zone.
                $time = CarbonImmutable::parse($attributes[$field])->setTimezone(config('app.timezone'));
                $attributes[$field] = $request->boolean('all_day') ? $time->startOfDay() : $time;
            }
        }

        return $attributes;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function members(): array
    {
        $workspace = $this->context->get() ?? abort(403);

        return $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
