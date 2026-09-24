<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MonitoringRule;
use App\Models\Person;
use App\Models\SocialHashtag;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Monitoring: the team's monitoring rules (managers).
 */
class MonitoringRuleController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(): Response
    {
        $this->authorizeManager();

        return Inertia::render('settings/monitoring', [
            'rules' => MonitoringRule::with('person:id,name')->orderBy('name')->get()->map(fn (MonitoringRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'include_terms' => $rule->include_terms,
                'exclude_terms' => $rule->exclude_terms ?? [],
                'person_id' => $rule->person_id,
                'person' => $rule->person?->name,
                'category' => $rule->category,
                'google_news' => $rule->google_news,
                'active' => $rule->active,
            ]),
            'people' => Person::orderBy('name')->get(['id', 'name']),
            'hashtags' => SocialHashtag::orderBy('tag')->get(['id', 'tag']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager();
        MonitoringRule::create($this->validated($request));

        return back();
    }

    public function update(Request $request, MonitoringRule $rule): RedirectResponse
    {
        $this->authorizeManager();
        $rule->update($this->validated($request, partial: true));

        return back();
    }

    public function destroy(MonitoringRule $rule): RedirectResponse
    {
        $this->authorizeManager();
        $rule->delete();

        return back();
    }

    /**
     * Terms are typed as one comma-separated line.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        // Empty inputs arrive as null (ConvertEmptyStringsToNull).
        $split = fn ($value) => $value === null ? [] : (is_string($value)
            ? collect(explode(',', $value))->map(fn (string $term) => trim($term))->filter()->unique()->values()->all()
            : $value);
        $request->merge(array_filter([
            'include_terms' => $request->exists('include_terms') ? $split($request->input('include_terms')) : null,
            'exclude_terms' => $request->exists('exclude_terms') ? $split($request->input('exclude_terms')) : null,
        ], fn ($value) => $value !== null));

        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'include_terms' => [$required, 'array', 'min:1', 'max:30'],
            'include_terms.*' => ['string', 'max:100'],
            'exclude_terms' => ['sometimes', 'array', 'max:30'],
            'exclude_terms.*' => ['string', 'max:100'],
            'person_id' => ['sometimes', 'nullable', 'uuid', function ($attribute, $value, $fail) {
                if ($value !== null && ! Person::whereKey($value)->exists()) {
                    $fail(__('The selected person does not exist.'));
                }
            }],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'google_news' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    private function authorizeManager(): void
    {
        Gate::authorize('manage-members', $this->context->get() ?? abort(403));
    }
}
