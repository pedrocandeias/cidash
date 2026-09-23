<?php

namespace Tests\Feature\Tasks;

use App\Enums\RelationType;
use App\Enums\TaskStatus;
use App\Enums\WorkspaceRole;
use App\Models\Link;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TaskAssigned;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $ana;

    private User $rui;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->ana = User::factory()->inWorkspace($this->workspace)->create(['name' => 'Ana']);
        $this->rui = User::factory()->inWorkspace($this->workspace)->create(['name' => 'Rui']);
    }

    /**
     * Create a task directly, as $creator, in $workspace.
     */
    private function task(array $attributes = [], ?User $creator = null, ?Workspace $workspace = null): Task
    {
        app(WorkspaceContext::class)->set($workspace ?? $this->workspace);
        $this->actingAs($creator ?? $this->ana);

        return Task::create(['title' => 'Tarefa', 'priority' => 'normal', 'status' => 'todo', ...$attributes]);
    }

    public function test_a_task_is_created_and_the_assignee_is_notified()
    {
        Notification::fake();

        $this->actingAs($this->ana)
            ->post(route('tasks.store'), ['title' => 'Responder ao JN', 'assigned_to' => $this->rui->id, 'deadline' => '2026-10-01'])
            ->assertSessionHasNoErrors();

        $task = Task::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Responder ao JN', $task->title);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame($this->ana->id, $task->record->created_by);
        Notification::assertSentTo($this->rui, TaskAssigned::class);
    }

    public function test_only_members_of_the_workspace_can_be_assigned()
    {
        $outsider = User::factory()->inWorkspace()->create();

        $this->actingAs($this->ana)
            ->post(route('tasks.store'), ['title' => 'X', 'assigned_to' => $outsider->id])
            ->assertSessionHasErrors('assigned_to');
    }

    public function test_a_task_can_be_created_from_another_record()
    {
        $source = $this->task(['title' => 'Notícia sobre o ranking']);

        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Preparar resposta', 'source_id' => $source->id]);

        $task = Task::where('title', 'Preparar resposta')->firstOrFail();
        $this->assertSame($source->id, $task->source_object_id);
        $this->assertTrue(Link::where('source_id', $task->id)->where('target_id', $source->id)->where('type', RelationType::OriginatedFrom)->exists());
    }

    public function test_lists_show_my_open_tasks_or_the_team_tasks()
    {
        $this->task(['title' => 'Minha', 'assigned_to' => $this->ana->id]);
        $this->task(['title' => 'Do Rui', 'assigned_to' => $this->rui->id]);
        $this->task(['title' => 'Feita', 'assigned_to' => $this->ana->id, 'status' => 'done']);

        $this->actingAs($this->ana)->get(route('tasks.index'))
            ->assertInertia(fn (Assert $page) => $page->component('tasks/index')
                ->has('tasks', 1)->where('tasks.0.title', 'Minha'));

        $this->actingAs($this->ana)->get(route('tasks.index', ['view' => 'team', 'status' => 'all']))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 3));
    }

    public function test_status_changes_set_the_completion_date_and_are_logged()
    {
        $task = $this->task();

        $this->actingAs($this->rui)->patch(route('tasks.update', $task), ['status' => 'done'])->assertSessionHasNoErrors();

        $task->refresh();
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame('updated', $task->record->activity()->latest('id')->first()->action);

        $this->actingAs($this->rui)->patch(route('tasks.update', $task), ['status' => 'todo']);
        $this->assertNull($task->refresh()->completed_at);
    }

    public function test_the_task_page_shows_comments_and_history()
    {
        $task = $this->task(['title' => 'Com comentários']);
        $this->actingAs($this->rui)->post(route('comments.store', $task->id), ['body' => 'Já liguei ao jornalista.']);

        $this->actingAs($this->ana)->get(route('tasks.show', $task))
            ->assertInertia(fn (Assert $page) => $page->component('tasks/show')
                ->where('task.title', 'Com comentários')
                ->where('comments.0.body', 'Já liguei ao jornalista.')
                ->where('comments.0.author', 'Rui')
                ->where('comments.0.can_delete', false)
                ->where('activity.0.action', 'created'));
    }

    public function test_only_the_creator_or_a_manager_can_delete_a_task()
    {
        $task = $this->task([], $this->ana);

        $this->actingAs($this->rui)->delete(route('tasks.destroy', $task))->assertForbidden();

        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_tasks_and_comments_of_other_workspaces_are_not_reachable()
    {
        $other = Workspace::factory()->create();
        $stranger = User::factory()->inWorkspace($other)->create();
        $theirs = $this->task(['title' => 'Da outra equipa'], $stranger, $other);

        $this->actingAs($this->ana)->get(route('tasks.show', $theirs))->assertNotFound();
        $this->actingAs($this->ana)->patch(route('tasks.update', $theirs), ['status' => 'done'])->assertNotFound();
        $this->actingAs($this->ana)->post(route('comments.store', $theirs->id), ['body' => 'olá'])->assertNotFound();
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'X', 'source_id' => $theirs->id])->assertNotFound();

        $this->assertSame(TaskStatus::Todo, Task::withoutGlobalScopes()->find($theirs->id)->status);
    }

    public function test_opening_a_notification_marks_it_read_and_switches_workspace()
    {
        $other = Workspace::factory()->create();
        $other->members()->attach($this->ana, ['role' => 'member']);
        $task = $this->task(['title' => 'Noutra equipa'], $this->rui, $other);
        $this->ana->notify(new TaskAssigned($task, $this->rui));
        $notification = $this->ana->notifications()->first();

        $this->actingAs($this->ana)
            ->get(route('notifications.open', $notification->id))
            ->assertRedirect(route('tasks.show', $task, absolute: false));

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertSame($other->id, $this->ana->refresh()->current_workspace_id);
    }
}
