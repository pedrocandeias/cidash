<?php

namespace Tests\Feature\Campaigns;

use App\Core\Links;
use App\Enums\RelationType;
use App\Enums\WorkspaceRole;
use App\Http\Controllers\CampaignTaskController;
use App\Models\Campaign;
use App\Models\ContentItem;
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

class CampaignTasksTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($this->workspace);
        $this->campaign = Campaign::create(['name' => 'Candidaturas 2027', 'status' => 'planning']);
    }

    public function test_a_task_to_create_content_creates_the_content_in_the_campaign()
    {
        Notification::fake();
        $colleague = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();

        $this->actingAs($this->member)->post(route('campaigns.tasks.store', $this->campaign->id), [
            'title' => 'Vídeo do Dia Aberto', 'assignees' => [$colleague->id], 'deadline' => '2026-10-10',
            'create_content' => true, 'format' => 'video',
        ])->assertSessionHasNoErrors();

        $content = ContentItem::sole();
        $task = Task::sole();
        $this->assertSame(['Vídeo do Dia Aberto', 'video', 'idea', [$colleague->id]], [$content->title, $content->format, $content->stage->value, $content->assigneeIds()]);
        $this->assertSame([$colleague->id], $task->assigneeIds());
        $this->assertTrue(Link::where(['source_id' => $content->id, 'target_id' => $this->campaign->id, 'type' => RelationType::PartOf])->exists());
        $this->assertSame($content->id, $task->source_object_id);
        Notification::assertSentTo($colleague, TaskAssigned::class);
    }

    public function test_the_campaign_shows_its_tasks_and_those_of_its_items_with_progress()
    {
        $content = ContentItem::create(['title' => 'Notícia', 'format' => 'news', 'stage' => 'idea']);
        app(Links::class)->link($content, $this->campaign, RelationType::PartOf);

        $this->actingAs($this->member)->post(route('campaigns.tasks.store', $this->campaign->id), ['title' => 'Reservar fotógrafo']);
        $this->actingAs($this->member)->post(route('campaigns.tasks.store', $this->campaign->id), ['title' => 'Rever a notícia', 'about' => $content->id]);
        $unrelated = Task::create(['title' => 'Outra coisa', 'priority' => 'normal', 'status' => 'todo']);
        Task::where('title', 'Reservar fotógrafo')->sole()->update(['status' => 'done']);

        $this->actingAs($this->member)->get(route('campaigns.show', $this->campaign->id))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tasks', 2)
                ->where('tasks.0.title', 'Rever a notícia')
                ->where('tasks.0.about', 'Notícia')
                ->where('tasks.1.status', 'done')
                ->has('parts', 1)
                ->where('relations', fn ($relations) => collect($relations)->every(fn ($relation) => $relation['record']['type'] !== 'task')));

        $this->assertNotContains($unrelated->id, collect(CampaignTaskController::tasksOf($this->campaign))->pluck('id'));
    }

    public function test_a_task_cannot_point_at_a_record_outside_the_campaign()
    {
        $elsewhere = ContentItem::create(['title' => 'Fora', 'format' => 'news', 'stage' => 'idea']);

        $this->actingAs($this->member)->post(route('campaigns.tasks.store', $this->campaign->id), ['title' => 'X', 'about' => $elsewhere->id])->assertStatus(422);
        $this->actingAs($this->member)->post(route('campaigns.tasks.store', $this->campaign->id), ['title' => 'X', 'create_content' => true])->assertSessionHasErrors('format');
        $this->assertSame(0, Task::count());
    }
}
