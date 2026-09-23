<?php

namespace App\Http\Controllers\Settings;

use App\Alerts\Evaluator;
use App\Enums\AlertSeverity;
use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Alerts: switch catalogue rules on or off and tune them (managers).
 */
class AlertRuleController extends Controller
{
    public function index(WorkspaceContext $context, Evaluator $evaluator): Response
    {
        $workspace = $context->get() ?? abort(403);
        Gate::authorize('manage-members', $workspace);
        $evaluator->install($workspace);

        return Inertia::render('settings/alerts', [
            'rules' => AlertRule::orderBy('id')->get()->map(fn (AlertRule $rule) => [
                'id' => $rule->id,
                'message' => $rule->type()->message(),
                'description' => $rule->type()->description(),
                'params' => collect($rule->type()->parameters())->mapWithKeys(fn ($default, $name) => [$name => (int) $rule->param($name)]),
                'severity' => $rule->severity->value,
                'active' => $rule->active,
            ]),
        ]);
    }

    public function update(Request $request, WorkspaceContext $context, AlertRule $rule): RedirectResponse
    {
        $workspace = $context->get() ?? abort(403);
        Gate::authorize('manage-members', $workspace);

        $params = array_keys($rule->type()->parameters());
        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'severity' => ['sometimes', Rule::enum(AlertSeverity::class)],
            'params' => ['sometimes', 'array:'.implode(',', $params)],
            'params.*' => ['integer', 'min:1', 'max:720'],
        ]);

        if (isset($validated['params'])) {
            $validated['params'] = array_map('intval', [...($rule->params ?? []), ...$validated['params']]);
        }
        $rule->update($validated);

        // Apply the change on the next page that shows alerts.
        Cache::forget("alerts:evaluated:{$workspace->id}");

        return back();
    }
}
