<?php

namespace App\Http\Controllers;

use App\Core\RecordTypes;
use App\Core\Search\Search;
use App\Models\Record;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search (⌘K) and record lookup for the relations panel.
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, Search $search, WorkspaceContext $context): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'exclude' => ['nullable', 'uuid'],
        ]);

        $workspace = $context->get() ?? abort(403);
        $hits = $search->search($workspace->id, $validated['q'], 20)
            ->reject(fn (array $hit) => $hit['id'] === ($validated['exclude'] ?? null));

        // Loaded through the workspace scope as a second guard.
        $records = Record::whereIn('id', $hits->pluck('id'))->get()->keyBy('id');

        return response()->json($hits
            ->filter(fn (array $hit) => $records->has($hit['id']))
            ->map(fn (array $hit) => [...RecordTypes::summary($records[$hit['id']]), 'snippet' => $hit['snippet']])
            ->values());
    }
}
