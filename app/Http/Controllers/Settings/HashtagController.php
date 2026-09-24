<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialHashtag;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Settings → Monitoring: the hashtags the team follows on social networks (managers).
 */
class HashtagController extends Controller
{
    public function store(Request $request, WorkspaceContext $context): RedirectResponse
    {
        Gate::authorize('manage-members', $context->get() ?? abort(403));

        // Several at once: "#uporto #feup, #fep".
        $tags = collect(preg_split('/[\s,;]+/', (string) $request->input('tags')) ?: [])
            ->map(fn (string $tag) => SocialHashtag::normalize($tag))
            ->filter()
            ->unique()
            ->values();
        $request->merge(['tags' => $tags->all()]);
        $request->validate([
            'tags' => ['required', 'array', 'min:1', 'max:30'],
            'tags.*' => ['string', 'max:100', 'regex:/^[\p{L}\p{N}_]+$/u'],
        ], ['tags.*.regex' => __('A hashtag has only letters, numbers and underscores.')]);

        foreach ($tags as $tag) {
            SocialHashtag::firstOrCreate(['tag' => $tag]);
        }

        return back();
    }

    public function destroy(WorkspaceContext $context, SocialHashtag $hashtag): RedirectResponse
    {
        Gate::authorize('manage-members', $context->get() ?? abort(403));

        $hashtag->delete();

        return back();
    }
}
