<?php

namespace App\Http\Controllers;

use App\Core\RecordPage;
use App\Enums\Priority;
use App\Http\Requests\NoticeRequest;
use App\Models\Notice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class NoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $archive = $request->query('view') === 'archive';

        $notices = Notice::query()
            ->with('record.creator:id,name')
            ->when(
                $archive,
                fn ($query) => $query->where(fn ($query) => $query->where('expires_at', '<=', now())->orWhere('published_at', '>', now())),
                fn ($query) => $query->active(),
            )
            ->orderByDesc('pinned')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Notice $notice) => $this->summary($notice));

        return Inertia::render('notices/index', [
            'notices' => $notices,
            'view' => $archive ? 'archive' : 'active',
            'can' => ['pin' => $request->user()->can('pin', Notice::class)],
        ]);
    }

    public function store(NoticeRequest $request): RedirectResponse
    {
        $this->ensureCanPin($request);

        $notice = Notice::create([
            ...$request->validated(),
            'published_at' => $request->input('published_at') ?: now(),
            'priority' => $request->input('priority', Priority::Normal->value),
        ]);

        return to_route('notices.show', $notice);
    }

    public function show(Request $request, Notice $notice, RecordPage $page): Response
    {
        $notice->load('record.creator:id,name');

        return Inertia::render('notices/show', [
            'notice' => $this->summary($notice),
            ...$page->for($notice->record, $request->user()),
            'can' => [
                'delete' => $request->user()->can('delete', $notice),
                'pin' => $request->user()->can('pin', Notice::class),
            ],
        ]);
    }

    public function update(NoticeRequest $request, Notice $notice): RedirectResponse
    {
        $this->ensureCanPin($request, $notice);

        $notice->update([
            ...$request->validated(),
            ...($request->has('published_at') ? ['published_at' => $request->input('published_at') ?: $notice->published_at] : []),
        ]);

        return back();
    }

    public function destroy(Notice $notice): RedirectResponse
    {
        Gate::authorize('delete', $notice);

        $notice->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notice deleted.')]);

        return to_route('notices.index');
    }

    /**
     * Only managers may change the pinned flag.
     */
    private function ensureCanPin(NoticeRequest $request, ?Notice $notice = null): void
    {
        if ($request->has('pinned') && $request->boolean('pinned') !== ($notice->pinned ?? false)) {
            Gate::authorize('pin', Notice::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Notice $notice): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'body' => $notice->body,
            'published_at' => $notice->published_at->toIso8601String(),
            'expires_at' => $notice->expires_at?->toIso8601String(),
            'priority' => $notice->priority->value,
            'pinned' => $notice->pinned,
            'active' => $notice->isActive(),
            'author' => $notice->record->creator?->name,
        ];
    }
}
