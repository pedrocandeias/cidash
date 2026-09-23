<?php

namespace Tests\Feature\Notifications;

use App\Briefing\DailyBriefing;
use App\Enums\WorkspaceRole;
use App\Mail\BriefingMail;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\Support\MailSettings;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->user = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($this->workspace);
    }

    private function configureEmail(): void
    {
        app(MailSettings::class)->save([
            'host' => 'smtp.up.pt', 'port' => 587, 'encryption' => 'starttls', 'username' => 'cidash',
            'password' => 'secret', 'from_address' => 'cidash@up.pt', 'from_name' => 'CIDASH',
        ]);
    }

    private function task(): Task
    {
        return Task::create(['title' => 'Responder ao JN', 'priority' => 'normal', 'status' => 'todo']);
    }

    public function test_notifications_stay_in_the_app_until_email_is_configured()
    {
        Notification::fake();

        $this->user->notify(new TaskAssigned($this->task(), null));

        Notification::assertSentTo($this->user, TaskAssigned::class, fn ($notification, array $channels) => $channels === ['database']);
    }

    public function test_notifications_also_go_by_email_as_each_user_prefers()
    {
        Notification::fake();
        $this->configureEmail();
        $task = $this->task();

        $this->user->notify(new TaskAssigned($task, null));
        Notification::assertSentTo($this->user, TaskAssigned::class, fn ($notification, array $channels) => $channels === ['database', 'mail']);

        $this->actingAs($this->user)->patch(route('notification-preferences.update'), ['assignments' => false, 'daily_briefing' => true])->assertRedirect();
        $this->assertFalse($this->user->refresh()->email_preferences['assignments']);
        $this->assertTrue($this->user->email_preferences['reminders'], 'untouched preferences keep their default');

        Notification::fake();
        $this->user->notify(new TaskAssigned($task, null));
        Notification::assertSentTo($this->user, TaskAssigned::class, fn ($notification, array $channels) => $channels === ['database']);
    }

    public function test_the_email_links_back_and_the_bell_keeps_the_same_data()
    {
        $manager = User::factory()->create(['name' => 'Ana Reis']);
        $notification = new TaskAssigned($this->task(), $manager);

        $mail = $notification->toMail($this->user);
        $this->assertSame('Foi-lhe atribuída uma tarefa: Responder ao JN', $mail->subject);
        $this->assertStringEndsWith('/tasks/'.Task::sole()->id, (string) $mail->actionUrl);
        $this->assertSame('Ana Reis', $notification->toArray($this->user)['by']);
    }

    public function test_briefings_are_emailed_to_those_who_asked()
    {
        Mail::fake();
        $this->configureEmail();
        $other = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        $this->user->forceFill(['email_preferences' => ['daily_briefing' => true]])->save();

        $this->artisan('cidash:generate-briefings')->assertSuccessful();

        Mail::assertSent(BriefingMail::class, fn (BriefingMail $mail) => $mail->hasTo($this->user->email));
        Mail::assertNotSent(BriefingMail::class, fn (BriefingMail $mail) => $mail->hasTo($other->email));
    }

    public function test_the_briefing_email_renders_its_sections()
    {
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->setTime(10, 0), 'priority' => 'normal', 'status' => 'confirmed']);
        $briefing = app(DailyBriefing::class)->generate($this->workspace);

        $html = (new BriefingMail($briefing, 'CI Reitoria'))->render();

        $this->assertStringContainsString('Hoje', $html);
        $this->assertStringContainsString('Dia Aberto', $html);
        $this->assertStringContainsString('Nada.', $html);
    }

    public function test_the_preferences_page_shows_the_defaults()
    {
        $this->actingAs($this->user)->get(route('notification-preferences.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('settings/notifications')
                ->where('preferences.assignments', true)
                ->where('preferences.daily_briefing', false)
                ->where('emailConfigured', false));
    }
}
