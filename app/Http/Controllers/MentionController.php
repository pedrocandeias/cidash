<?php

namespace App\Http\Controllers;

use App\Core\RecordPage;
use App\Enums\TriageStatus;
use App\Models\Mention;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mentions inbox: articles that matched the team's monitoring rules.
 */
class MentionController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['relevant', 'irrelevant', 'all'], true) ? $request->query('status') : 'new';

        return Inertia::render('mentions/index', [
            'mentions' => Mention::with('rule:id,name')
                ->when($status !== 'all', fn ($query) => $query->where('review_status', $status))
                ->orderByRaw('published_at is null')
                ->orderByDesc('published_at')
                ->limit(300)
                ->get()
                ->map(fn (Mention $mention) => [
                    'id' => $mention->id,
                    'headline' => $mention->headline,
                    'outlet' => $mention->outlet,
                    'url' => $mention->url,
                    'published_at' => $mention->published_at?->toIso8601String(),
                    'matched_keyword' => $mention->matched_keyword,
                    'rule' => $mention->rule?->name,
                    'status' => $mention->review_status->value,
                ]),
            'status' => $status,
            'counts' => Mention::selectRaw('review_status, count(*) as total')->groupBy('review_status')->pluck('total', 'review_status'),
        ]);
    }

    public function show(Request $request, Mention $mention, RecordPage $page): Response
    {
        $mention->load('rule:id,name');

        return Inertia::render('mentions/show', [
            'mention' => [
                'id' => $mention->id,
                'headline' => $mention->headline,
                'excerpt' => $mention->excerpt,
                'outlet' => $mention->outlet,
                'url' => $mention->url,
                'published_at' => $mention->published_at?->toIso8601String(),
                'matched_keyword' => $mention->matched_keyword,
                'rule' => $mention->rule?->name,
                'category' => $mention->category,
                'status' => $mention->review_status->value,
                'relevance' => $mention->relevance,
            ],
            'members' => $this->members(),
            ...$page->for($mention->record, $request->user()),
        ]);
    }

    public function update(Request $request, Mention $mention): RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => ['sometimes', Rule::enum(TriageStatus::class)],
            'relevance' => ['sometimes', 'nullable', 'in:low,medium,high'],
        ]);

        $mention->update($validated);

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
