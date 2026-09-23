<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SourceKind;
use App\Http\Controllers\Controller;
use App\Models\NewsItem;
use App\Models\Source;
use App\Monitoring\Ingestor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Sources: the global source catalogue and its health (super admin).
 */
class SourceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/sources', [
            'sources' => Source::withCount(['workspaces'])->orderBy('name')->get()->map(fn (Source $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'kind' => $source->kind->value,
                'url' => $source->url,
                'config' => $source->config,
                'active' => $source->active,
                'poll_minutes' => $source->poll_minutes,
                'last_fetched_at' => $source->last_fetched_at?->toIso8601String(),
                'last_error' => $source->last_error,
                'consecutive_failures' => $source->consecutive_failures,
                'subscribers' => $source->workspaces_count,
                'items' => NewsItem::where('source_id', $source->id)->count(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Source::create($this->validated($request));

        return back();
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $source->update($this->validated($request, partial: true));

        return back();
    }

    public function destroy(Source $source): RedirectResponse
    {
        $source->delete();

        return back();
    }

    public function fetch(Source $source, Ingestor $ingestor): RedirectResponse
    {
        $new = $ingestor->run($source);

        Inertia::flash('toast', $source->last_error
            ? ['type' => 'error', 'message' => __('Collection failed: :error', ['error' => $source->last_error])]
            : ['type' => 'success', 'message' => __(':count new articles.', ['count' => $new])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'kind' => [$required, Rule::enum(SourceKind::class)],
            'url' => [$required, 'url', 'max:2048'],
            'config' => ['sometimes', 'nullable', 'json'],
            'active' => ['sometimes', 'boolean'],
            'poll_minutes' => ['sometimes', 'integer', 'between:5,1440'],
        ]);

        if (array_key_exists('config', $validated)) {
            $validated['config'] = $validated['config'] ? json_decode($validated['config'], true) : null;
        }

        return $validated;
    }
}
