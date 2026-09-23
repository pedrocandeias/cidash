<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Core\RecordTypes;
use App\Enums\RelationType;
use App\Models\Link;
use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Relations panel: link records of the current workspace and find them by title.
 */
class LinkController extends Controller
{
    public function store(Request $request, Record $record, Links $links): RedirectResponse
    {
        $validated = $request->validate([
            'target_id' => ['required', 'uuid'],
            'type' => ['sometimes', Rule::enum(RelationType::class)],
            // Link the other record to this one instead (e.g. content part_of this campaign).
            'reverse' => ['sometimes', 'boolean'],
        ]);

        $other = Record::findOrFail((string) $validated['target_id']);
        $type = RelationType::tryFrom($validated['type'] ?? '') ?? RelationType::RelatedTo;

        $request->boolean('reverse')
            ? $links->link($other, $record, $type)
            : $links->link($record, $other, $type);

        return back();
    }

    public function destroy(Link $link): RedirectResponse
    {
        $link->delete();

        return back();
    }

    /**
     * Records whose title contains the query (until full-text search exists).
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'exclude' => ['nullable', 'uuid'],
        ]);

        $records = Record::query()
            ->where('title', 'like', '%'.$validated['q'].'%')
            ->when($validated['exclude'] ?? null, fn ($query, $id) => $query->whereKeyNot($id))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Record $record) => RecordTypes::summary($record));

        return response()->json($records);
    }
}
