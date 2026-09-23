<?php

namespace App\Http\Middleware;

use App\Support\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'workspace' => function () use ($request) {
                // Pages outside the "workspace" middleware (e.g. settings) show the last used one.
                $workspace = app(WorkspaceContext::class)->get() ?? $request->user()?->currentWorkspace;

                return $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'role' => $request->user()?->roleIn($workspace)?->value,
                ] : null;
            },
            'locale' => app()->getLocale(),
            'translations' => fn () => trans()->getLoader()->load(app()->getLocale(), '*', '*'),
        ];
    }
}
