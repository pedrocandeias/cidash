<?php

namespace Tests\Feature\Console;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_workspace()
    {
        $this->artisan('cidash:create-workspace', ['name' => 'CI Reitoria', '--slug' => 'reitoria'])
            ->assertSuccessful();

        $this->assertDatabaseHas('workspaces', ['name' => 'CI Reitoria', 'slug' => 'reitoria']);

        $this->artisan('cidash:create-workspace', ['name' => 'Outra', '--slug' => 'reitoria'])
            ->assertFailed();
    }

    public function test_it_creates_a_super_admin_in_a_workspace_with_a_set_password_link()
    {
        $workspace = Workspace::factory()->create(['slug' => 'reitoria']);

        $this->artisan('cidash:create-user', [
            'email' => 'Ana@UP.pt',
            'name' => 'Ana',
            '--super-admin' => true,
            '--workspace' => 'reitoria',
            '--role' => 'manager',
        ])
            ->expectsOutputToContain('/reset-password/')
            ->assertSuccessful();

        $user = User::where('email', 'ana@up.pt')->firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertSame(WorkspaceRole::Manager, $user->roleIn($workspace));
    }

    public function test_it_rejects_invalid_input()
    {
        User::factory()->create(['email' => 'ana@up.pt']);

        $this->artisan('cidash:create-user', ['email' => 'ana@up.pt', 'name' => 'Ana'])->assertFailed();
        $this->artisan('cidash:create-user', ['email' => 'rui@up.pt', 'name' => 'Rui', '--role' => 'boss'])->assertFailed();
        $this->artisan('cidash:create-user', ['email' => 'rui@up.pt', 'name' => 'Rui', '--workspace' => 'nope'])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'rui@up.pt']);
    }
}
