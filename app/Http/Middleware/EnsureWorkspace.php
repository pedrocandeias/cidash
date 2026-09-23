<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }

    private function resolve(User $user): ?Workspace
    {
        $current = $user->currentWorkspace;

        if ($current && ($user->is_super_admin || $user->roleIn($current))) {
            return $current;
        }

        return $user->workspaces()->oldest('workspace_user.id')->first()
            ?? ($user->is_super_admin ? Workspace::query()->oldest('id')->first() : null);
    }
}
