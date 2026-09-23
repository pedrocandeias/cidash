<?php

namespace App\Http\Middleware;

use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the workspace the user works in: the last one they used if they can
 * still access it, otherwise their first membership. The super admin can enter
 * any workspace. Users without a workspace cannot use the app.
 */
class EnsureWorkspace
{
    public function __construct(private WorkspaceContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $workspace = $this->resolve($user);

        abort_if($workspace === null, 403, __('You are not a member of any team yet.'));

        if ($user->current_workspace_id !== $workspace->id) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        $this->context->set($workspace);

        $this->logSuperAdminAccess($request, $user, $workspace);

        return $next($request);
    }

    /**
     * Super admin visits to a workspace they do not belong to are audited, once per session.
     */
    private function logSuperAdminAccess(Request $request, User $user, Workspace $workspace): void
    {
        $key = "workspace_access_logged.{$workspace->id}";

        if (! $user->is_super_admin || $user->roleIn($workspace) !== null || $request->session()->has($key)) {
            return;
        }

        Activity::log('workspace.accessed_by_super_admin');
        $request->session()->put($key, true);
    }

    private function resolve(User $user): ?Workspace
    {
        $current = $user->currentWorkspace;

        // The super admin can still open an archived team; members cannot.
        if ($current && ($user->is_super_admin || ($user->roleIn($current) && ! $current->isArchived()))) {
            return $current;
        }

        return $user->workspaces()->whereNull('archived_at')->oldest('workspace_user.id')->first()
            ?? ($user->is_super_admin ? Workspace::active()->oldest('id')->first() : null);
    }
}
