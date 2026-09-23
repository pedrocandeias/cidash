<?php

namespace App\Http\Controllers;

use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user guide (resources/guide/<locale>.md) and the "First steps" checklist.
 */
class GuideController extends Controller
{
    public function show(): Response
    {
        $path = resource_path('guide/'.app()->getLocale().'.md');
        $markdown = File::get(File::exists($path) ? $path : resource_path('guide/pt_PT.md'));

        // Our own file, not user input: HTML in it is trusted.
        return Inertia::render('guide', ['html' => Str::markdown($markdown, ['heading_permalink' => false])]);
    }

    public function dismissOnboarding(WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->get() ?? abort(403);
        Gate::authorize('manage-members', $workspace);

        $workspace->forceFill(['onboarding_dismissed_at' => now()])->save();

        return back();
    }
}
