<?php

namespace App\Http\Controllers\Settings;

use App\Core\ExpertiseAreas;
use App\Core\Tags;
use App\Core\Vocabulary;
use App\Http\Controllers\Controller;
use App\Models\ExpertiseArea;
use App\Models\Tag;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Managers tidy the team's vocabularies: rename, merge and delete tags and
 * expertise areas (ARCHITECTURE.md §2.3 "Termos normalizados").
 */
class TermsController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Tags $tags, ExpertiseAreas $areas): Response
    {
        $this->authorize();

        $list = fn (string $model, Vocabulary $vocabulary) => $model::orderBy('name')->get()
            ->map(fn (Model $term) => ['id' => $term->getKey(), 'name' => $term->getAttribute('name'), 'usage' => $vocabulary->usage($term)]);

        return Inertia::render('settings/terms', [
            'tags' => $list(Tag::class, $tags),
            'areas' => $list(ExpertiseArea::class, $areas),
        ]);
    }

    public function update(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorize();
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
        [$vocabulary, $model] = $this->resolve($kind);

        $vocabulary->rename($model::findOrFail($id), $validated['name']);

        return back();
    }

    public function merge(Request $request, string $kind, int $id): RedirectResponse
    {
        $this->authorize();
        $validated = $request->validate(['into' => ['required', 'integer']]);
        [$vocabulary, $model] = $this->resolve($kind);

        $vocabulary->merge($model::findOrFail($id), $model::findOrFail((int) $validated['into']));

        return back();
    }

    public function destroy(string $kind, int $id): RedirectResponse
    {
        $this->authorize();
        [, $model] = $this->resolve($kind);

        $model::findOrFail($id)->delete();

        return back();
    }

    /**
     * @return array{0: Vocabulary, 1: class-string<Model>}
     */
    private function resolve(string $kind): array
    {
        return match ($kind) {
            'tags' => [app(Tags::class), Tag::class],
            'areas' => [app(ExpertiseAreas::class), ExpertiseArea::class],
            default => abort(404),
        };
    }

    private function authorize(): void
    {
        Gate::authorize('manage-members', $this->context->get() ?? abort(403));
    }
}
