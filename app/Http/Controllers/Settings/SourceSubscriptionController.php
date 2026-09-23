<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Source;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Sources: which catalogue sources the team follows (managers).
 */
class SourceSubscriptionController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(): Response
    {
        $workspace = $this->authorizeManager();
        $subscriptions = $workspace->sources()->get()->keyBy('id');

        return Inertia::render('settings/sources', [
            'sources' => Source::where('active', true)->orderBy('name')->get()->map(fn (Source $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'kind' => $source->kind->value,
                'subscribed' => $subscriptions->has($source->id),
                'is_priority' => (bool) ($subscriptions[$source->id]->pivot->is_priority ?? false),
            ]),
        ]);
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $workspace = $this->authorizeManager();
        $validated = $request->validate(['subscribed' => ['required', 'boolean'], 'is_priority' => ['sometimes', 'boolean']]);

        if ($validated['subscribed']) {
            $workspace->sources()->syncWithoutDetaching([$source->id => ['is_priority' => $validated['is_priority'] ?? false]]);
        } else {
            $workspace->sources()->detach($source->id);
        }

        return back();
    }

    private function authorizeManager(): Workspace
    {
        $workspace = $this->context->get() ?? abort(403);
        Gate::authorize('manage-members', $workspace);

        return $workspace;
    }
}
