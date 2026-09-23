<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Enums\RelationType;
use App\Models\Link;
use App\Models\Record;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Relations panel: link records of the current workspace.
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
}
