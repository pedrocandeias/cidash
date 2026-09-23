<?php

namespace Tests\Feature\Workspaces;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EnsureWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_work_in_their_workspace()
    {
        $workspace = Workspace::factory()->create(['name' => 'CI Reitoria']);
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Editor)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.id', $workspace->id)
                ->where('workspace.name', 'CI Reitoria')
                ->where('workspace.role', 'editor'));

        $this->assertSame($workspace->id, $user->refresh()->current_workspace_id);
    }

    public function test_users_without_a_workspace_are_forbidden()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_the_last_used_workspace_is_kept()
    {
        $first = Workspace::factory()->create();
        $second = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($first)->inWorkspace($second)->create();
        $user->forceFill(['current_workspace_id' => $second->id])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('workspace.id', $second->id));
    }

    public function test_a_workspace_the_user_left_is_not_used()
    {
        $old = Workspace::factory()->create();
        $current = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($current)->create();
        $user->forceFill(['current_workspace_id' => $old->id])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('workspace.id', $current->id));
    }

    public function test_super_admins_can_work_in_a_workspace_they_do_not_belong_to()
    {
        $workspace = Workspace::factory()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('workspace.id', $workspace->id)
                ->where('workspace.role', null));

        $this->assertTrue($admin->hasRole($workspace, WorkspaceRole::Manager));
    }
}
