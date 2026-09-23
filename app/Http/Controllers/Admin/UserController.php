<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WorkspaceRole;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamMembers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Users: every account of the instance (super admin only).
 */
class UserController extends Controller
{
    public function __construct(private TeamMembers $members) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/users', [
            'users' => User::with('workspaces')->orderBy('name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_super_admin' => $user->is_super_admin,
                'pending' => $user->activated_at === null,
                'deactivated' => $user->deactivated_at !== null,
                'two_factor' => $user->two_factor_confirmed_at !== null,
                'is_me' => $user->is($request->user()),
                'memberships' => $user->workspaces->map(fn (Workspace $workspace) => [
                    'workspace_id' => $workspace->id,
                    'workspace' => $workspace->name,
                    'role' => $user->roleIn($workspace)?->value,
                ])->values(),
            ]),
            'workspaces' => Workspace::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'is_super_admin' => ['sometimes', 'boolean'],
        ]);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => __('You cannot change your own access.')]);
        }

        if (array_key_exists('active', $validated)) {
            $user->forceFill(['deactivated_at' => $validated['active'] ? null : now()])->save();
            Activity::logGlobal($validated['active'] ? 'user.reactivated' : 'user.deactivated', $user);
        }

        if (array_key_exists('is_super_admin', $validated)) {
            $user->forceFill(['is_super_admin' => $validated['is_super_admin']])->save();
            Activity::logGlobal($validated['is_super_admin'] ? 'user.super_admin_granted' : 'user.super_admin_revoked', $user);
        }

        return back();
    }

    /**
     * For users who lost their authenticator: they can sign in with the password and set it up again.
     */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Activity::logGlobal('user.two_factor_reset', $user);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Two-factor authentication reset for :name.', ['name' => $user->name])]);

        return back();
    }

    public function addMembership(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'workspace_id' => ['required', 'integer', Rule::exists('workspaces', 'id')],
            'role' => ['required', Rule::enum(WorkspaceRole::class)],
        ]);

        $this->members->addExisting(Workspace::findOrFail((int) $validated['workspace_id']), $user, WorkspaceRole::from($validated['role']));

        return back();
    }

    public function updateMembership(Request $request, User $user, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate(['role' => ['required', Rule::enum(WorkspaceRole::class)]]);

        $this->members->changeRole($workspace, $user, WorkspaceRole::from($validated['role']));

        return back();
    }

    public function removeMembership(User $user, Workspace $workspace): RedirectResponse
    {
        $this->members->remove($workspace, $user);

        return back();
    }
}
