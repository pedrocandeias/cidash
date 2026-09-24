<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Enums\ContentStage;
use App\Enums\Priority;
use App\Enums\RelationType;
use App\Enums\TaskStatus;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\Link;
use App\Models\Record;
use App\Models\Task;
use App\Support\Assignments;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Tasks of a campaign: created from the campaign page, about the campaign or one of
 * its items, optionally together with the content the task is about.
 */
class CampaignTaskController extends Controller
{
    public function store(Request $request, Campaign $campaign, Links $links, Options $options): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in($options->keys('task_type'))],
            ...Assignments::rules(),
            'deadline' => ['nullable', 'date'],
            'about' => ['nullable', 'uuid'],
            'create_content' => ['sometimes', 'boolean'],
            'format' => ['required_if:create_content,true', 'nullable', Rule::in($options->keys('content_format'))],
        ]);

        // A task is about the campaign or one of its items, never about another team's record.
        $about = $campaign->record;
        if (! empty($validated['about']) && $validated['about'] !== $campaign->id) {
            $about = in_array($validated['about'], self::itemIds($campaign), true)
                ? Record::findOrFail($validated['about'])
                : abort(422, __('That item is not part of the campaign.'));
        }

        $task = DB::transaction(function () use ($request, $validated, $campaign, $links, $about) {
            if ($request->boolean('create_content')) {
                $content = ContentItem::create([
                    'title' => $validated['title'],
                    'format' => $validated['format'],
                    'stage' => ContentStage::Idea,
                    'due_at' => $validated['deadline'] ?? null,
                ]);
                $links->link($content, $campaign, RelationType::PartOf);
                // The people doing the task are responsible for the content too.
                Assignments::sync($content, $validated['assignees'] ?? [], $request->user());
                $about = $content->record;
            }

            $task = Task::create([
                'title' => $validated['title'],
                'type' => $validated['type'] ?? null,
                'start_date' => today(),
                'deadline' => $validated['deadline'] ?? null,
                'priority' => Priority::Normal,
                'status' => TaskStatus::Todo,
                'source_object_id' => $about->id,
            ]);
            $links->link($task, $about, RelationType::OriginatedFrom);

            return $task;
        });

        Assignments::sync($task, $validated['assignees'] ?? [], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return back();
    }

    /**
     * Records that are part of the campaign (its events and content).
     *
     * @return array<int, string>
     */
    public static function itemIds(Campaign $campaign): array
    {
        return Link::where('target_id', $campaign->id)
            ->where('type', RelationType::PartOf)
            ->pluck('source_id')
            ->all();
    }

    /**
     * Tasks linked to the campaign or to any of its items, open ones first.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function tasksOf(Campaign $campaign): array
    {
        $ids = [$campaign->id, ...self::itemIds($campaign)];
        $taskIds = Link::query()
            ->where(fn ($query) => $query->whereIn('source_id', $ids)->orWhereIn('target_id', $ids))
            ->get(['source_id', 'target_id'])
            ->flatMap(fn (Link $link) => [$link->source_id, $link->target_id])
            ->diff($ids)
            ->unique()
            ->values();
        $titles = Record::whereKey($ids)->pluck('title', 'id');

        return Task::with(['assignees:id,name', 'record'])
            ->whereKey($taskIds)
            ->get()
            ->sortBy(fn (Task $task) => [$task->status === TaskStatus::Done || $task->status === TaskStatus::Cancelled, $task->deadline->timestamp ?? PHP_INT_MAX])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'type' => $task->type,
                'status' => $task->status->value,
                'deadline' => $task->deadline?->toIso8601String(),
                'assignees' => $task->assigneeNames(),
                // The item it is about, when it is not the campaign itself.
                'about' => $task->source_object_id !== null && $task->source_object_id !== $campaign->id ? ($titles[$task->source_object_id] ?? null) : null,
            ])
            ->values()
            ->all();
    }
}
