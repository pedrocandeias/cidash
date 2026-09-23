<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AlertStatus;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Source;
use App\Models\Workspace;
use App\Support\TeamMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administration → Workspaces (super admin): every team at a glance, creating,
 * renaming and archiving teams, naming managers, and the state of ingestion.
 */
class WorkspaceController extends Controller
{
    public function index(): Response
    {
        $count = fn ($query) => $query->selectRaw('workspace_id, count(*) as total')->groupBy('workspace_id')->pluck('total', 'workspace_id');

        $members = $count(DB::table('workspace_user'));
        $records = $count(DB::table('objects'));
        $alerts = $count(DB::table('alerts')->where('status', '!=', AlertStatus::Resolved->value));
        $inReview = DB::table('content_items')->join('objects', 'objects.id', '=', 'content_items.id')
            ->where('content_items.stage', 'review')
            ->selectRaw('objects.workspace_id, count(*) as total')->groupBy('objects.workspace_id')->pluck('total', 'workspace_id');
        $lastActivity = DB::table('activity_log')->whereNotNull('workspace_id')
            ->selectRaw('workspace_id, max(created_at) as last')->groupBy('workspace_id')->pluck('last', 'workspace_id');
        $managers = DB::table('workspace_user')->join('users', 'users.id', '=', 'workspace_user.user_id')
            ->where('workspace_user.role', WorkspaceRole::Manager->value)
            ->orderBy('users.name')
            ->get(['workspace_user.workspace_id', 'users.name'])
            ->groupBy('workspace_id');

        return Inertia::render('admin/workspaces', [
            'workspaces' => Workspace::orderByRaw('archived_at is not null')->orderBy('name')->get()->map(fn (Workspace $workspace) => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'archived' => $workspace->isArchived(),
                'members' => (int) ($members[$workspace->id] ?? 0),
                'records' => (int) ($records[$workspace->id] ?? 0),
                'open_alerts' => (int) ($alerts[$workspace->id] ?? 0),
                'in_review' => (int) ($inReview[$workspace->id] ?? 0),
                'last_activity' => isset($lastActivity[$workspace->id]) ? now()->parse($lastActivity[$workspace->id])->toIso8601String() : null,
                'managers' => ($managers[$workspace->id] ?? collect())->pluck('name')->values(),
            ]),
            'ingestion' => [
                'items_24h' => DB::table('news_items')->where('retrieved_at', '>=', now()->subDay())->count(),
                'items_7d' => DB::table('news_items')->where('retrieved_at', '>=', now()->subWeek())->count(),
                'sources' => Source::where('active', true)->count(),
                'failing' => Source::where('active', true)->where('consecutive_failures', '>=', 3)->count(),
                'last_fetch' => Source::max('last_fetched_at') !== null ? now()->parse(Source::max('last_fetched_at'))->toIso8601String() : null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('workspaces', 'name')]]);
        $slug = Str::slug($validated['name']) ?: 'equipa';
        for ($base = $slug, $n = 2; Workspace::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        $workspace = Workspace::create(['name' => $validated['name'], 'slug' => $slug]);
        Activity::logGlobal('workspace.created', null, ['workspace' => $workspace->name]);

        return back();
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120', Rule::unique('workspaces', 'name')->ignore($workspace->id)],
            'archived' => ['sometimes', 'boolean'],
        ]);

        if (isset($validated['name'])) {
            $workspace->update(['name' => $validated['name']]);
        }
        if (array_key_exists('archived', $validated)) {
            $workspace->forceFill(['archived_at' => $validated['archived'] ? now() : null])->save();
            Activity::logGlobal($validated['archived'] ? 'workspace.archived' : 'workspace.restored', null, ['workspace' => $workspace->name]);
        }

        return back();
    }

    /**
     * Names a manager: an existing account keeps its other teams; a new one is invited.
     */
    public function addManager(Request $request, Workspace $workspace, TeamMembers $members): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = $members->add($workspace, $validated['email'], $validated['name'], WorkspaceRole::Manager);

        if ($result['link'] !== null && ! $result['emailed']) {
            Inertia::flash('invitationLink', $result['link']);
        }

        return back();
    }
}
