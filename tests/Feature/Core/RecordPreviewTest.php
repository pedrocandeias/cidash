<?php

namespace Tests\Feature\Core;

use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\Person;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->user = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create(['name' => 'Ana Reis']);
        app(WorkspaceContext::class)->set($this->workspace);
    }

    public function test_the_preview_shows_the_main_fields_of_each_type()
    {
        $task = Task::create(['title' => 'Kit de imprensa', 'description' => 'Juntar <b>fotos</b> e texto.', 'deadline' => '2026-10-01', 'priority' => 'high', 'status' => 'in_progress']);
        $task->syncAssignees([$this->user->id]);

        $this->actingAs($this->user)->getJson(route('records.preview', $task->id))
            ->assertOk()
            ->assertJson([
                'id' => $task->id,
                'label' => 'Task',
                'title' => 'Kit de imprensa',
                'url' => route('tasks.show', $task->id, absolute: false),
                'status' => ['value' => 'in_progress', 'list' => 'task'],
                'fields' => [
                    ['label' => 'Deadline', 'value' => '2026-10-01', 'kind' => 'date'],
                    ['label' => 'Assignees', 'value' => 'Ana Reis', 'kind' => 'text'],
                    ['label' => 'Priority', 'value' => 'high', 'kind' => 'priority'],
                ],
                'body' => 'Juntar fotos e texto.',
                'counts' => ['comments' => 0, 'attachments' => 0],
            ]);
    }

    public function test_every_record_type_can_be_previewed()
    {
        $records = [
            CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now(), 'priority' => 'normal', 'status' => 'confirmed', 'location' => 'Reitoria']),
            PressRequest::create(['subject' => 'Ranking', 'received_at' => now(), 'status' => 'received', 'journalist' => 'Rita']),
            ContentItem::create(['title' => 'Notícia', 'format' => 'news', 'stage' => 'idea']),
            Person::create(['name' => 'Maria Silva', 'affiliation' => 'FEUP']),
        ];

        foreach ($records as $record) {
            $this->actingAs($this->user)->getJson(route('records.preview', $record->getKey()))->assertOk()->assertJsonStructure(['title', 'fields', 'status', 'tags', 'counts']);
        }
        $this->actingAs($this->user)->getJson(route('records.preview', $records[3]->getKey()))
            ->assertJsonPath('fields.0', ['label' => 'Affiliation', 'value' => 'FEUP', 'kind' => 'text']);
    }

    public function test_records_of_other_teams_cannot_be_previewed()
    {
        $task = Task::create(['title' => 'Interna', 'priority' => 'normal', 'status' => 'todo']);
        $outsider = User::factory()->inWorkspace(Workspace::factory()->create(), WorkspaceRole::Manager)->create();

        $this->actingAs($outsider)->getJson(route('records.preview', $task->id))->assertNotFound();
    }
}
