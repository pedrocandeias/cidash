<?php

namespace App\Support;

use App\Enums\WorkspaceRole;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Membership changes, enforcing that every workspace keeps at least one manager
 * (ARCHITECTURE.md §2.6). Managers act only on their own workspace.
 */
class TeamMembers
{
    public function __construct(private Invitations $invitations) {}

    /**
     * Add a person to the workspace. Existing accounts are simply added; new ones
     * are created and invited.
     *
     * @return array{user: User, link: ?string, emailed: bool}
     */
    public function add(Workspace $workspace, string $email, string $name, WorkspaceRole $role): array
    {
        $email = Str::lower(trim($email));

        $user = DB::transaction(function () use ($workspace, $email, $name, $role) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                // Unusable until the invitation is accepted.
                'password' => Str::password(64),
            ]);

            if ($user->roleIn($workspace) !== null) {
                throw ValidationException::withMessages(['email' => __('This person is already a member of the team.')]);
            }

            $workspace->members()->attach($user, ['role' => $role]);

            return $user;
        });

        if ($user->activated_at !== null) {
            return ['user' => $user, 'link' => null, 'emailed' => false];
        }

        return ['user' => $user, ...$this->invitations->send($user, $workspace)];
    }

    /**
     * Add an existing account to a workspace (super admin).
     */
    public function addExisting(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        if ($user->roleIn($workspace) !== null) {
            throw ValidationException::withMessages(['workspace' => __('This person is already a member of the team.')]);
        }

        $workspace->members()->attach($user, ['role' => $role]);
    }

    public function changeRole(Workspace $workspace, User $user, WorkspaceRole $role): void
    {
        DB::transaction(function () use ($workspace, $user, $role) {
            if ($role !== WorkspaceRole::Manager) {
                $this->ensureAnotherManager($workspace, $user);
            }

            $workspace->members()->updateExistingPivot($user->id, ['role' => $role]);
        });
    }

    public function remove(Workspace $workspace, User $user): void
    {
        DB::transaction(function () use ($workspace, $user) {
            $this->ensureAnotherManager($workspace, $user);

            $workspace->members()->detach($user);

            if ($user->current_workspace_id === $workspace->id) {
                $user->forceFill(['current_workspace_id' => null])->save();
            }
        });
    }

    /**
     * Workspaces where the user is the only manager.
     *
     * @return Collection<int, Workspace>
     */
    public function workspacesManagedOnlyBy(User $user): Collection
    {
        return $user->workspaces()
            ->wherePivot('role', WorkspaceRole::Manager->value)
            ->get()
            ->filter(fn (Workspace $workspace) => $this->managerCount($workspace) === 1)
            ->values();
    }

    private function ensureAnotherManager(Workspace $workspace, User $user): void
    {
        if ($user->roleIn($workspace) === WorkspaceRole::Manager && $this->managerCount($workspace) === 1) {
            throw ValidationException::withMessages(['role' => __('The team must keep at least one manager.')]);
        }
    }

    private function managerCount(Workspace $workspace): int
    {
        return Membership::query()
            ->where('workspace_id', $workspace->id)
            ->where('role', WorkspaceRole::Manager->value)
            ->count();
    }
}
