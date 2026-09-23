<?php

namespace Tests\Feature\Settings;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\InvitationNotification;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create(['name' => 'CI Reitoria']);
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    private function newMember(array $data = []): array
    {
        return ['name' => 'Rui Silva', 'email' => 'rui@up.pt', 'role' => 'member', ...$data];
    }

    public function test_only_managers_can_manage_the_team()
    {
        foreach ([WorkspaceRole::Member, WorkspaceRole::Editor] as $role) {
            $user = User::factory()->inWorkspace($this->workspace, $role)->create();

            $this->actingAs($user)->get(route('team.index'))->assertForbidden();
            $this->actingAs($user)->post(route('team.store'), $this->newMember())->assertForbidden();
        }

        $this->assertDatabaseMissing('users', ['email' => 'rui@up.pt']);
    }

    public function test_managers_see_the_members_of_their_team()
    {
        $pending = User::factory()->pending()->inWorkspace($this->workspace, WorkspaceRole::Editor)->create(['name' => 'Ana']);
        User::factory()->inWorkspace()->create(['name' => 'Outra equipa']);

        $this->actingAs($this->manager)
            ->get(route('team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/team')
                ->where('team', 'CI Reitoria')
                ->has('members', 2)
                ->where('members.0.id', $pending->id)
                ->where('members.0.role', 'editor')
                ->where('members.0.pending', true));
    }

    public function test_adding_a_new_person_creates_a_pending_account_and_an_invitation_link()
    {
        Notification::fake();

        $this->actingAs($this->manager)
            ->post(route('team.store'), $this->newMember(['email' => 'Rui@UP.pt', 'role' => 'editor']))
            ->assertRedirect(route('team.index'))
            ->assertInertiaFlash('invitationLink');

        $user = User::where('email', 'rui@up.pt')->firstOrFail();
        $this->assertNull($user->activated_at);
        $this->assertSame(WorkspaceRole::Editor, $user->roleIn($this->workspace));
        Notification::assertNothingSent();
    }

    public function test_invitations_are_emailed_when_email_is_configured()
    {
        Notification::fake();
        app(MailSettings::class)->save([
            'host' => 'smtp.up.pt', 'port' => 587, 'encryption' => 'starttls', 'username' => null,
            'password' => null, 'from_address' => 'cidash@up.pt', 'from_name' => 'CIDASH',
        ]);

        $this->actingAs($this->manager)
            ->post(route('team.store'), $this->newMember())
            ->assertInertiaFlashMissing('invitationLink');

        Notification::assertSentTo(User::where('email', 'rui@up.pt')->first(), InvitationNotification::class);
    }

    public function test_existing_accounts_are_added_without_an_invitation()
    {
        $existing = User::factory()->inWorkspace()->create(['email' => 'rui@up.pt']);

        $this->actingAs($this->manager)
            ->post(route('team.store'), $this->newMember())
            ->assertInertiaFlashMissing('invitationLink');

        $this->assertSame(WorkspaceRole::Member, $existing->roleIn($this->workspace));
        $this->assertCount(2, $existing->workspaces);
    }

    public function test_a_member_cannot_be_added_twice()
    {
        User::factory()->inWorkspace($this->workspace)->create(['email' => 'rui@up.pt']);

        $this->actingAs($this->manager)
            ->post(route('team.store'), $this->newMember())
            ->assertSessionHasErrors('email');
    }

    public function test_roles_can_be_changed_but_the_team_keeps_a_manager()
    {
        $member = User::factory()->inWorkspace($this->workspace)->create();

        $this->actingAs($this->manager)->patch(route('team.update', $member), ['role' => 'manager']);
        $this->assertSame(WorkspaceRole::Manager, $member->roleIn($this->workspace));

        $this->actingAs($this->manager)->patch(route('team.update', $this->manager), ['role' => 'member']);
        $this->assertSame(WorkspaceRole::Member, $this->manager->roleIn($this->workspace));

        $this->actingAs($member)
            ->patch(route('team.update', $member), ['role' => 'editor'])
            ->assertSessionHasErrors('role');
        $this->assertSame(WorkspaceRole::Manager, $member->roleIn($this->workspace));
    }

    public function test_members_can_be_removed_but_not_the_last_manager()
    {
        $member = User::factory()->inWorkspace($this->workspace)->create();
        $member->forceFill(['current_workspace_id' => $this->workspace->id])->save();

        $this->actingAs($this->manager)->delete(route('team.destroy', $member))->assertRedirect(route('team.index'));
        $this->assertNull($member->roleIn($this->workspace));
        $this->assertNull($member->refresh()->current_workspace_id);
        $this->assertDatabaseHas('users', ['id' => $member->id]);

        $this->actingAs($this->manager)
            ->delete(route('team.destroy', $this->manager))
            ->assertSessionHasErrors('role');
        $this->assertSame(WorkspaceRole::Manager, $this->manager->roleIn($this->workspace));
    }

    public function test_members_of_other_workspaces_cannot_be_touched()
    {
        $other = Workspace::factory()->create();
        $outsider = User::factory()->pending()->inWorkspace($other, WorkspaceRole::Manager)->create();

        $this->actingAs($this->manager)->patch(route('team.update', $outsider), ['role' => 'member'])->assertNotFound();
        $this->actingAs($this->manager)->delete(route('team.destroy', $outsider))->assertNotFound();
        $this->actingAs($this->manager)->post(route('team.invitation', $outsider))->assertNotFound();

        $this->assertSame(WorkspaceRole::Manager, $outsider->roleIn($other));
    }

    public function test_invitations_can_be_resent_to_pending_members_only()
    {
        $pending = User::factory()->pending()->inWorkspace($this->workspace)->create();
        $active = User::factory()->inWorkspace($this->workspace)->create();

        $this->actingAs($this->manager)
            ->post(route('team.invitation', $pending))
            ->assertInertiaFlash('invitationLink');

        $this->actingAs($this->manager)->post(route('team.invitation', $active))->assertNotFound();
    }

    public function test_super_admins_can_manage_any_team()
    {
        $admin = User::factory()->superAdmin()->create();
        $admin->forceFill(['current_workspace_id' => $this->workspace->id])->save();

        $this->actingAs($admin)->get(route('team.index'))->assertOk();
    }
}
