<?php

namespace Tests\Feature\Admin;

use App\Enums\WorkspaceRole;
use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_only_super_admins_manage_users()
    {
        $manager = User::factory()->inWorkspace(null, WorkspaceRole::Manager)->create();
        $other = User::factory()->create();

        $this->actingAs($manager)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($manager)->patch(route('admin.users.update', $other), ['is_super_admin' => true])->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/users')->has('users', 3));
    }

    public function test_deactivated_accounts_cannot_sign_in_and_lose_their_session()
    {
        $rui = User::factory()->inWorkspace()->create(['email' => 'rui@up.pt']);

        $this->actingAs($this->admin)->patch(route('admin.users.update', $rui), ['active' => false])->assertSessionHasNoErrors();
        $this->assertNotNull($rui->refresh()->deactivated_at);
        $this->assertTrue(Activity::withoutGlobalScopes()->where('action', 'user.deactivated')->where('subject_user_id', $rui->id)->exists());

        $this->actingAs($rui)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'rui@up.pt', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($this->admin)->patch(route('admin.users.update', $rui), ['active' => true]);
        auth()->logout();
        $this->post(route('login.store'), ['email' => 'rui@up.pt', 'password' => 'password']);
        $this->assertAuthenticatedAs($rui);
    }

    public function test_super_admins_cannot_change_their_own_access()
    {
        $this->actingAs($this->admin)->patch(route('admin.users.update', $this->admin), ['is_super_admin' => false])->assertSessionHasErrors('user');
        $this->assertTrue($this->admin->refresh()->is_super_admin);
    }

    public function test_two_factor_can_be_reset()
    {
        $rui = User::factory()->withTwoFactor()->create();

        $this->actingAs($this->admin)->post(route('admin.users.two-factor-reset', $rui));

        $this->assertNull($rui->refresh()->two_factor_confirmed_at);
        $this->assertNull($rui->two_factor_secret);
    }

    public function test_memberships_are_managed_across_teams_keeping_a_manager()
    {
        $feup = Workspace::factory()->create();
        $manager = User::factory()->inWorkspace($feup, WorkspaceRole::Manager)->create();
        $rui = User::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.users.memberships.store', $rui), ['workspace_id' => $feup->id, 'role' => 'editor']);
        $this->assertSame(WorkspaceRole::Editor, $rui->roleIn($feup));

        $this->actingAs($this->admin)->patch(route('admin.users.memberships.update', [$rui, $feup]), ['role' => 'manager']);
        $this->assertSame(WorkspaceRole::Manager, $rui->roleIn($feup));

        $this->actingAs($this->admin)->delete(route('admin.users.memberships.destroy', [$manager, $feup]))->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->delete(route('admin.users.memberships.destroy', [$rui, $feup]))->assertSessionHasErrors('role');
        $this->assertSame(WorkspaceRole::Manager, $rui->roleIn($feup));
    }
}
