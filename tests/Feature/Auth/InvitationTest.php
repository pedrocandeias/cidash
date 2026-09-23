<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Support\Invitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function invite(User $user): string
    {
        $link = app(Invitations::class)->send($user)['link'];

        return basename(parse_url($link, PHP_URL_PATH));
    }

    public function test_the_invitation_page_is_shown()
    {
        $user = User::factory()->pending()->create();
        $token = $this->invite($user);

        $this->get(route('invitation.show', ['token' => $token, 'email' => $user->email]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/accept-invitation')
                ->where('email', $user->email)
                ->where('token', $token));
    }

    public function test_accepting_sets_the_password_activates_and_logs_in()
    {
        $user = User::factory()->pending()->inWorkspace()->create();
        $token = $this->invite($user);

        $this->travel(3)->days();

        $this->post(route('invitation.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'uma-palavra-passe-longa',
            'password_confirmation' => 'uma-palavra-passe-longa',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertNotNull($user->activated_at);
        $this->assertTrue(Hash::check('uma-palavra-passe-longa', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_or_expired_invitations_are_rejected()
    {
        $user = User::factory()->pending()->create();
        $token = $this->invite($user);

        $data = ['email' => $user->email, 'password' => 'uma-palavra-passe-longa', 'password_confirmation' => 'uma-palavra-passe-longa'];

        $this->post(route('invitation.store'), [...$data, 'token' => 'wrong'])->assertSessionHasErrors('email');

        $this->travel(8)->days();
        $this->post(route('invitation.store'), [...$data, 'token' => $token])->assertSessionHasErrors('email');

        $this->assertNull($user->refresh()->activated_at);
        $this->assertGuest();
    }

    public function test_resetting_the_password_also_activates_a_pending_user()
    {
        $user = User::factory()->pending()->create();

        app(ResetUserPassword::class)->reset($user, [
            'password' => 'uma-palavra-passe-longa',
            'password_confirmation' => 'uma-palavra-passe-longa',
        ]);

        $this->assertNotNull($user->refresh()->activated_at);
    }
}
