<?php

namespace App\Http\Controllers;

use App\Core\Tags;
use App\Core\Terms;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    /**
     * Existing tags matching what is typed, and near-miss spellings ("Queria dizer…?").
     */
    public function suggest(Request $request, Tags $tags): JsonResponse
    {
        $validated = $request->validate(['q' => ['required', 'string', 'max:50']]);
        $normalized = Terms::normalize($validated['q']);

        return response()->json([
            'matches' => Tag::where('normalized_name', 'like', '%'.$normalized.'%')
                ->orderBy('name')->limit(8)->pluck('name'),
            'similar' => $tags->similarTo($validated['q'])->pluck('name'),
        ]);
    }
}
