<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The workspace selector: people in several teams (and the super admin) choose where they work.
 */
class WorkspaceSwitchController extends Controller
{
    public function __invoke(Request $request, Workspace $workspace): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->is_super_admin || ($user->roleIn($workspace) !== null && ! $workspace->isArchived()), 403);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        $user->setRelation('currentWorkspace', $workspace);

        return to_route('dashboard');
    }
}
