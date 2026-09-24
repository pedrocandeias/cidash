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
use App\Support\Assignments;
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
            ->post(route('tasks.store'), ['title' => 'Responder ao JN', 'assignees' => [$this->rui->id], 'deadline' => '2026-10-01'])
            ->assertSessionHasNoErrors();

        $task = Task::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Responder ao JN', $task->title);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame($this->ana->id, $task->record->created_by);
        Notification::assertSentTo($this->rui, TaskAssigned::class);
    }

    public function test_a_new_task_starts_on_its_creation_day_and_the_start_can_be_changed()
    {
        $this->travelTo('2026-09-24 10:00');

        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Traduzir o discurso'])->assertSessionHasNoErrors();
        $task = Task::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('2026-09-24', $task->start_date->toDateString());

        $this->actingAs($this->ana)->patch(route('tasks.update', $task), ['start_date' => '2026-09-28'])->assertSessionHasNoErrors();
        $task->refresh();
        $this->assertSame('2026-09-28', $task->start_date->toDateString());

        // The creation date stays as it was, and the page shows both.
        $this->actingAs($this->ana)->get(route('tasks.show', $task))
            ->assertInertia(fn (Assert $page) => $page
                ->where('task.start_date', '2026-09-28')
                ->where('task.created_at', $task->record->created_at->toIso8601String()));
    }

    public function test_the_deadline_has_a_time()
    {
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Vídeo', 'deadline' => '2026-10-01T15:30'])->assertSessionHasNoErrors();

        $task = Task::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('2026-10-01 15:30', $task->deadline->format('Y-m-d H:i'));
    }

    public function test_a_task_has_people_responsible_and_co_responsible()
    {
        Notification::fake();
        $rita = User::factory()->inWorkspace($this->workspace)->create(['name' => 'Rita']);

        $this->actingAs($this->ana)
            ->post(route('tasks.store'), ['title' => 'Artigo', 'assignees' => [$this->rui->id], 'co_assignees' => [$rita->id]])
            ->assertSessionHasNoErrors();
        $task = Task::withoutGlobalScopes()->firstOrFail();

        // The co-responsible is told too and sees the task among theirs.
        Notification::assertSentTo($rita, TaskAssigned::class);
        $this->actingAs($rita)->get(route('tasks.show', $task))
            ->assertInertia(fn (Assert $page) => $page
                ->where('task.assignees', [['id' => $this->rui->id, 'name' => 'Rui']])
                ->where('task.co_assignees', [['id' => $rita->id, 'name' => 'Rita']]));
        $this->actingAs($rita)->get(route('tasks.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1));

        // Changing only the people responsible keeps the co-responsible.
        $this->actingAs($this->ana)->patch(route('tasks.update', $task), ['assignees' => [$this->ana->id]]);
        $task->load('assignees');
        $this->assertSame([$rita->id], array_column(Assignments::present($task, 'co'), 'id'));
        $this->assertSame([$this->ana->id], array_column(Assignments::present($task, 'lead'), 'id'));

        $this->actingAs($this->ana)
            ->patch(route('tasks.update', $task), ['co_assignees' => [User::factory()->inWorkspace()->create()->id]])
            ->assertSessionHasErrors('co_assignees.0');
    }

    public function test_only_members_of_the_workspace_can_be_assigned()
    {
        $outsider = User::factory()->inWorkspace()->create();

        $this->actingAs($this->ana)
            ->post(route('tasks.store'), ['title' => 'X', 'assignees' => [$outsider->id]])
            ->assertSessionHasErrors('assignees.0');
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
        $this->task(['title' => 'Minha'])->syncAssignees([$this->ana->id]);
        $this->task(['title' => 'Do Rui'])->syncAssignees([$this->rui->id]);
        $this->task(['title' => 'Feita', 'status' => 'done'])->syncAssignees([$this->ana->id]);

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

    public function test_tasks_have_a_type_from_the_team_list_and_can_be_filtered_by_it()
    {
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Traduzir o comunicado', 'type' => 'translation'])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Discurso do Reitor', 'type' => 'speech'])->assertSessionHasNoErrors();
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'Sem tipologia']);
        $this->actingAs($this->ana)->post(route('tasks.store'), ['title' => 'X', 'type' => 'nope'])->assertSessionHasErrors('type');

        $this->actingAs($this->ana)->get(route('tasks.index', ['view' => 'team', 'type' => 'translation']))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.type', 'translation')->where('filters.type', 'translation'));
        $this->actingAs($this->ana)->get(route('tasks.index', ['view' => 'team']))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 3)->has('options.task_type', 8));
    }
}
