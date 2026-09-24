<?php

namespace App\Http\Controllers;

use App\Core\Tags;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Collections of assets are tags: several assets are added to or taken out of one at once.
 */
class AssetCollectionController extends Controller
{
    public function store(Request $request, Tags $tags): RedirectResponse
    {
        $validated = $this->validated($request);
        $tag = $tags->findOrCreate($validated['name']);

        // The workspace scope keeps assets of other teams out.
        Asset::with('record')->whereKey($validated['assets'])->get()
            ->each(fn (Asset $asset) => $asset->record->tags()->syncWithoutDetaching([$tag->getKey()]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Collection :name updated.', ['name' => $tag->getAttribute('name')])]);

        return back();
    }

    public function destroy(Request $request, Tags $tags): RedirectResponse
    {
        $validated = $this->validated($request);
        $tag = $tags->findOrCreate($validated['name']);

        Asset::with('record')->whereKey($validated['assets'])->get()
            ->each(fn (Asset $asset) => $asset->record->tags()->detach($tag->getKey()));

        return back();
    }

    /**
     * @return array{name: string, assets: array<int, string>}
     */
    private function validated(Request $request): array
    {
        /** @var array{name: string, assets: array<int, string>} */
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'assets' => ['required', 'array', 'max:300'],
            'assets.*' => ['uuid'],
        ]);
    }
}
