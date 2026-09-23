<?php

namespace Tests\Feature\Calendar;

use App\Models\CalendarEvent;
use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ReminderDue;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemindersLinksAndTagsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->ana = User::factory()->inWorkspace($this->workspace)->create();
    }

    private function in(Workspace $workspace, User $user): void
    {
        app(WorkspaceContext::class)->set($workspace);
        $this->actingAs($user);
    }

    private function event(string $title = 'Dia Aberto'): CalendarEvent
    {
        $this->in($this->workspace, $this->ana);

        return CalendarEvent::create(['title' => $title, 'type' => 'institutional', 'start_at' => now()->addDays(3), 'priority' => 'normal', 'status' => 'confirmed']);
    }

    public function test_due_reminders_are_delivered_once()
    {
        Notification::fake();
        $event = $this->event();

        $this->actingAs($this->ana)->post(route('reminders.store', $event->id), ['remind_at' => now()->addMinutes(10)->toIso8601String()])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post(route('reminders.store', $event->id), ['remind_at' => now()->subMinute()->toIso8601String()])->assertSessionHasErrors('remind_at');

        app(WorkspaceContext::class)->set($this->workspace);
        $this->assertSame(1, Reminder::count());

        $this->travel(11)->minutes();
        app()->forgetScopedInstances();
        $this->artisan('cidash:send-reminders')->assertSuccessful();
        $this->artisan('cidash:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($this->ana, ReminderDue::class, 1);
    }

    public function test_reminder_times_sent_in_utc_are_stored_in_the_app_time_zone()
    {
        $event = $this->event();

        $this->actingAs($this->ana)->post(route('reminders.store', $event->id), ['remind_at' => '2030-01-15T09:00:00.000Z']);
        $this->actingAs($this->ana)->post(route('reminders.store', $event->id), ['remind_at' => '2030-07-15T09:00:00.000Z']);

        app(WorkspaceContext::class)->set($this->workspace);
        $this->assertSame(['2030-01-15 09:00', '2030-07-15 10:00'], Reminder::orderBy('remind_at')->get()->map(fn ($r) => $r->remind_at->format('Y-m-d H:i'))->all());
    }

    public function test_reminders_of_other_users_cannot_be_deleted()
    {
        $event = $this->event();
        $reminder = Reminder::create(['object_id' => $event->id, 'user_id' => $this->ana->id, 'remind_at' => now()->addDay()]);
        $rui = User::factory()->inWorkspace($this->workspace)->create();

        $this->actingAs($rui)->delete(route('reminders.destroy', $reminder))->assertForbidden();
    }

    public function test_records_are_linked_and_found_by_title_within_the_workspace()
    {
        $event = $this->event('Dia Aberto');
        $task = Task::create(['title' => 'Preparar Dia Aberto', 'priority' => 'normal', 'status' => 'todo']);

        $other = Workspace::factory()->create();
        $this->in($other, User::factory()->inWorkspace($other)->create());
        $theirs = Task::create(['title' => 'Dia Aberto da FEUP', 'priority' => 'normal', 'status' => 'todo']);

        $found = $this->actingAs($this->ana)->getJson(route('records.search', ['q' => 'aberto', 'exclude' => $event->id]))->json('*.id');
        $this->assertSame([$task->id], $found);

        $this->actingAs($this->ana)->post(route('links.store', $event->id), ['target_id' => $task->id])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post(route('links.store', $event->id), ['target_id' => $theirs->id])->assertNotFound();

        $this->in($this->workspace, $this->ana);
        $this->actingAs($this->ana)->get(route('tasks.show', $task))
            ->assertInertia(fn ($page) => $page->where('relations.0.record.title', 'Dia Aberto')->where('relations.0.record.url', route('events.show', $event, absolute: false)));
    }

    public function test_tag_suggestions_include_matches_and_near_misses()
    {
        $event = $this->event();
        $this->actingAs($this->ana)->patch(route('events.update', $event), ['tags' => ['Astronomia', 'Astrofísica']]);

        $this->actingAs($this->ana)->getJson(route('tags.suggest', ['q' => 'astro']))
            ->assertJsonPath('matches', ['Astrofísica', 'Astronomia']);

        $this->actingAs($this->ana)->getJson(route('tags.suggest', ['q' => 'Astrnomia']))
            ->assertJsonPath('similar', ['Astronomia']);
    }
}
