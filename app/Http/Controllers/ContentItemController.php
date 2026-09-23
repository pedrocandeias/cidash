<?php

namespace App\Http\Controllers;

use App\Core\RecordPage;
use App\Enums\ContentStage;
use App\Enums\WorkspaceRole;
use App\Http\Requests\ContentItemRequest;
use App\Models\ContentItem;
use App\Models\User;
use App\Notifications\ContentAwaitingReview;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContentItemController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $archived = $request->boolean('archived');

        $items = ContentItem::query()
            ->with('owner:id,name')
            ->when(! $archived, fn ($query) => $query->where('stage', '!=', ContentStage::Archived))
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->latest()
            ->get()
            ->map(fn (ContentItem $item) => $this->summary($item));

        return Inertia::render('content/index', [
            'items' => $items,
            'showArchived' => $archived,
            'members' => $this->members(),
            'can' => ['approve' => $request->user()->can('approve', ContentItem::class)],
        ]);
    }

    public function store(ContentItemRequest $request): RedirectResponse
    {
        $stage = ContentStage::tryFrom((string) $request->input('stage')) ?? ContentStage::Idea;
        $this->ensureCanMove($request->user(), ContentStage::Idea, $stage);

        $item = ContentItem::create([...$request->validated(), 'stage' => $stage]);

        $this->notifyIfInReview($item, $request->user());

        return to_route('content.show', $item);
    }

    public function show(Request $request, ContentItem $content, RecordPage $page): Response
    {
        $content->load('owner:id,name');

        return Inertia::render('content/show', [
            'item' => [
                ...$this->summary($content),
                'brief' => $content->brief,
                'owner_id' => $content->owner_id,
                'publish_at' => $content->publish_at?->toIso8601String(),
                'published_url' => $content->published_url,
            ],
            'members' => $this->members(),
            ...$page->for($content->record, $request->user()),
            'can' => [
                'delete' => $request->user()->can('delete', $content),
                'approve' => $request->user()->can('approve', ContentItem::class),
            ],
        ]);
    }

    public function update(ContentItemRequest $request, ContentItem $content): RedirectResponse
    {
        $from = $content->stage;
        $to = ContentStage::tryFrom((string) $request->input('stage')) ?? $from;
        $this->ensureCanMove($request->user(), $from, $to);

        $content->update($request->validated());

        if ($from !== $to) {
            $this->notifyIfInReview($content, $request->user());
        }

        return back();
    }

    public function destroy(ContentItem $content): RedirectResponse
    {
        Gate::authorize('delete', $content);

        $content->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Content deleted.')]);

        return to_route('content.index');
    }

    private function ensureCanMove(User $user, ContentStage $from, ContentStage $to): void
    {
        if (ContentStage::isApproval($from, $to) && ! $user->can('approve', ContentItem::class)) {
            throw ValidationException::withMessages(['stage' => __('Only editors and managers can approve content.')]);
        }
    }

    /**
     * Editors and managers are told when content enters review.
     */
    private function notifyIfInReview(ContentItem $item, User $actor): void
    {
        if ($item->stage !== ContentStage::Review) {
            return;
        }

        $workspace = $this->context->get() ?? abort(403);

        $workspace->members()->get()
            ->filter(fn (User $user) => ! $user->is($actor) && $user->hasRole($workspace, WorkspaceRole::Editor))
            ->each(fn (User $user) => $user->notify(new ContentAwaitingReview($item, $actor)));
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ContentItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'format' => $item->format->value,
            'channels' => $item->channels ?? [],
            'stage' => $item->stage->value,
            'stage_changed_at' => $item->stage_changed_at->toIso8601String(),
            'due_at' => $item->due_at?->toDateString(),
            'owner' => $item->owner ? ['id' => $item->owner->id, 'name' => $item->owner->name] : null,
        ];
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
