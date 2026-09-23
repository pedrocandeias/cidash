<?php

namespace App\Http\Controllers;

use App\Core\Tags;
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

        return response()->json($tags->suggest($validated['q']));
    }
}
