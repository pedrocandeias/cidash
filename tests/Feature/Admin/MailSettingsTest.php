<?php

namespace Tests\Feature\Admin;

use App\Mail\TestMail;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function validSettings(array $overrides = []): array
    {
        return [
            'host' => 'smtp.up.pt',
            'port' => 587,
            'encryption' => 'starttls',
            'username' => 'cidash',
            'password' => 'secret-password',
            'from_address' => 'cidash@up.pt',
            'from_name' => 'CIDASH',
            ...$overrides,
        ];
    }

    public function test_only_super_admins_can_access_email_settings()
    {
        $this->actingAs(User::factory()->inWorkspace()->create())
            ->get(route('admin.email.edit'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.email.update'), $this->validSettings())
            ->assertForbidden();

        $this->assertDatabaseCount('settings', 0);
    }

    public function test_settings_are_saved_with_an_encrypted_password_that_is_never_shown()
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->put(route('admin.email.update'), $this->validSettings())
            ->assertRedirect(route('admin.email.edit'));

        $stored = Setting::findOrFail('mail')->value;
        $this->assertNotSame('secret-password', $stored['password']);

        $this->actingAs($admin)
            ->get(route('admin.email.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/email')
                ->where('settings.host', 'smtp.up.pt')
                ->where('settings.has_password', true)
                ->missing('settings.password'));
    }

    public function test_an_empty_password_keeps_the_stored_one()
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->put(route('admin.email.update'), $this->validSettings());

        $this->actingAs($admin)->put(route('admin.email.update'), $this->validSettings(['password' => '', 'host' => 'mail.up.pt']));

        app(MailSettings::class)->apply();
        $this->assertSame('mail.up.pt', config('mail.mailers.smtp.host'));
        $this->assertSame('secret-password', config('mail.mailers.smtp.password'));
    }

    public function test_stored_settings_configure_the_mailer()
    {
        app(MailSettings::class)->save($this->validSettings(['encryption' => 'ssl', 'port' => 465]));

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('cidash@up.pt', config('mail.from.address'));
    }

    public function test_invalid_settings_are_rejected()
    {
        $this->actingAs(User::factory()->superAdmin()->create())
            ->put(route('admin.email.update'), $this->validSettings(['port' => 'abc', 'encryption' => 'none', 'from_address' => 'nope']))
            ->assertSessionHasErrors(['port', 'encryption', 'from_address']);
    }

    public function test_a_test_email_is_sent_to_the_admin()
    {
        Mail::fake();
        $admin = User::factory()->superAdmin()->create(['email' => 'admin@up.pt']);
        app(MailSettings::class)->save($this->validSettings());

        $this->actingAs($admin)->post(route('admin.email.test'))->assertRedirect(route('admin.email.edit'));

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('admin@up.pt'));
    }

    public function test_no_test_email_is_sent_before_the_settings_are_saved()
    {
        Mail::fake();

        $this->actingAs(User::factory()->superAdmin()->create())->post(route('admin.email.test'));

        Mail::assertNothingSent();
    }
}
