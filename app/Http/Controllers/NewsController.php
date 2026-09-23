<?php

namespace App\Http\Controllers;

use App\Core\RecordPage;
use App\Enums\TriageStatus;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * News inbox of the current workspace: one line per story, triage per item.
 */
class NewsController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['relevant', 'irrelevant', 'all'], true) ? $request->query('status') : 'new';
        $workspace = $this->context->get() ?? abort(403);

        $states = NewsItemState::query()
            ->with('newsItem.source')
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->join('news_items', 'news_items.id', '=', 'news_item_states.news_item_id')
            ->orderByDesc(DB::raw('coalesce(news_items.published_at, news_items.retrieved_at)'))
            ->select('news_item_states.*')
            ->limit(300)
            ->get();

        $priority = DB::table('workspace_sources')->where('workspace_id', $workspace->id)->where('is_priority', true)->pluck('source_id')->all();

        // One line per story (the most recent item), with how many items it groups.
        $lines = $states->groupBy(fn (NewsItemState $state) => $state->newsItem->story_id ?? 'item-'.$state->id)
            ->map(function ($group) use ($priority) {
                $state = $group->first();
                $item = $state->newsItem;

                return [
                    'id' => $state->id,
                    'headline' => $item->headline,
                    'outlet' => $item->outlet,
                    'url' => $item->url,
                    'published_at' => ($item->published_at ?? $item->retrieved_at)->toIso8601String(),
                    'status' => $state->status->value,
                    'story_count' => $group->count(),
                    'outlets' => $group->map(fn (NewsItemState $other) => $other->newsItem->outlet)->filter()->unique()->values(),
                    'priority' => $group->contains(fn (NewsItemState $other) => in_array($other->newsItem->source_id, $priority, true)),
                ];
            })
            ->values();

        return Inertia::render('news/index', [
            'lines' => $lines,
            'status' => $status,
            'counts' => NewsItemState::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'members' => $this->members(),
        ]);
    }

    public function show(Request $request, NewsItemState $news, RecordPage $page): Response
    {
        $news->load('newsItem.source');
        $item = $news->newsItem;

        $sameStory = $item->story_id === null ? collect() : NewsItemState::with('newsItem')
            ->whereIn('news_item_id', NewsItem::where('story_id', $item->story_id)->whereKeyNot($item->id)->select('id'))
            ->get()
            ->map(fn (NewsItemState $state) => ['id' => $state->id, 'headline' => $state->newsItem->headline, 'outlet' => $state->newsItem->outlet]);

        return Inertia::render('news/show', [
            'news' => [
                'id' => $news->id,
                'headline' => $item->headline,
                'summary' => $item->summary,
                'outlet' => $item->outlet,
                'url' => $item->url,
                'published_at' => $item->published_at?->toIso8601String(),
                'retrieved_at' => $item->retrieved_at->toIso8601String(),
                'status' => $news->status->value,
                'relevance' => $news->relevance,
            ],
            'sameStory' => $sameStory,
            'members' => $this->members(),
            ...$page->for($news->record, $request->user()),
        ]);
    }

    public function update(Request $request, NewsItemState $news): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(TriageStatus::class)],
            'relevance' => ['sometimes', 'nullable', 'in:low,medium,high'],
            'whole_story' => ['sometimes', 'boolean'],
        ]);

        $targets = $request->boolean('whole_story') && $news->newsItem->story_id !== null
            ? NewsItemState::whereIn('news_item_id', NewsItem::where('story_id', $news->newsItem->story_id)->select('id'))->get()
            : collect([$news]);

        foreach ($targets as $target) {
            $target->update([
                ...array_diff_key($validated, ['whole_story' => true]),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        return back();
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
