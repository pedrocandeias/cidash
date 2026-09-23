<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoPublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_routes_do_not_exist()
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_unverified_users_can_use_the_app()
    {
        $user = User::factory()->unverified()->inWorkspace()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }
}
