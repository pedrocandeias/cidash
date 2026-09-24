<?php

namespace Tests\Feature\Core;

use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\RecordAssigned;
use App\Notifications\TaskAssigned;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssigneesTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $ana;

    private User $rui;

    private User $ines;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->workspace = Workspace::factory()->create();
        [$this->ana, $this->rui, $this->ines] = User::factory()->count(3)->inWorkspace($this->workspace, WorkspaceRole::Member)->create()->all();
        app(WorkspaceContext::class)->set($this->workspace);
    }

    public function test_a_task_has_several_people_responsible_and_each_new_one_is_notified()
    {
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Kit de imprensa', 'assignees' => [$this->ana->id, $this->rui->id]])->assertSessionHasNoErrors();
        $task = Task::sole();

        $this->assertEqualsCanonicalizing([$this->ana->id, $this->rui->id], $task->assigneeIds());
        Notification::assertSentTo($this->rui, TaskAssigned::class);
        Notification::assertNotSentTo($this->ana, TaskAssigned::class, 'not the person who assigned it');

        Notification::fake();
        $this->actingAs($this->ana)->patch(route('tasks.update', $task->id), ['assignees' => [$this->rui->id, $this->ines->id]]);
        Notification::assertSentTo($this->ines, TaskAssigned::class);
        Notification::assertNotSentTo($this->rui, TaskAssigned::class, 'already responsible');

        $this->actingAs($this->ana)->patch(route('tasks.update', $task->id), ['assignees' => []]);
        $this->assertSame([], $task->refresh()->assigneeIds());
        $this->actingAs($this->ana)->patch(route('tasks.update', $task->id), ['title' => 'Kit']);
        $this->assertSame([], $task->refresh()->assigneeIds(), 'an update without assignees leaves them alone');

        $this->actingAs($this->rui)->get(route('tasks.index'))->assertInertia(fn (Assert $page) => $page->has('tasks', 0));
    }

    public function test_events_content_and_press_notify_the_people_made_responsible()
    {
        $this->actingAs($this->ana)->post(route('events.store'), [
            'title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'all_day' => false, 'priority' => 'normal', 'status' => 'confirmed', 'assignees' => [$this->rui->id, $this->ines->id],
        ])->assertSessionHasNoErrors();

        Notification::assertSentTo([$this->rui, $this->ines], RecordAssigned::class);
        $this->assertCount(2, CalendarEvent::sole()->assignees);

        $this->actingAs($this->ana)->post(route('content.store'), ['title' => 'Vídeo', 'format' => 'video', 'assignees' => [$this->rui->id]])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post(route('press.store'), ['subject' => 'Ranking', 'assignees' => [$this->ines->id]])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($this->ines, RecordAssigned::class, 2);
    }

    public function test_only_team_members_can_be_made_responsible()
    {
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create())->create();

        $this->actingAs($this->ana)->post(route('content.store'), ['title' => 'X', 'format' => 'news', 'assignees' => [$outsider->id]])
            ->assertSessionHasErrors('assignees.0');
    }
}
