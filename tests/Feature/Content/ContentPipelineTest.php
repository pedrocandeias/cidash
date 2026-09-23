<?php

namespace Tests\Feature\Content;

use App\Enums\ContentStage;
use App\Enums\WorkspaceRole;
use App\Models\ContentItem;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ContentAwaitingReview;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    private User $editor;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace)->create();
        $this->editor = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Editor)->create();
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    private function item(string $stage = 'idea', array $attributes = []): ContentItem
    {
        app(WorkspaceContext::class)->set($this->workspace);
        $this->actingAs($this->member);

        return ContentItem::create(['title' => 'Notícia', 'format' => 'news', 'stage' => $stage, ...$attributes]);
    }

    public function test_content_is_created_as_an_idea_with_channels()
    {
        $this->actingAs($this->member)->post(route('content.store'), [
            'title' => 'Vídeo do Dia Aberto', 'format' => 'video', 'channels' => ['youtube', 'instagram'],
        ])->assertSessionHasNoErrors();

        $item = ContentItem::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(ContentStage::Idea, $item->stage);
        $this->assertSame(['youtube', 'instagram'], $item->channels);
        $this->assertNotNull($item->stage_changed_at);
    }

    public function test_members_cannot_approve_or_skip_the_review()
    {
        $item = $this->item('review');

        foreach (['approved', 'scheduled', 'published'] as $stage) {
            $this->actingAs($this->member)->patch(route('content.update', $item), ['stage' => $stage])->assertSessionHasErrors('stage');
        }
        $this->actingAs($this->member)->post(route('content.store'), ['title' => 'X', 'format' => 'news', 'stage' => 'published'])->assertSessionHasErrors('stage');

        $this->assertSame(ContentStage::Review, $item->refresh()->stage);
    }

    public function test_editors_approve_and_members_continue_after_approval()
    {
        $item = $this->item('review');
        $before = $item->stage_changed_at;
        $this->travel(1)->hour();

        $this->actingAs($this->editor)->patch(route('content.update', $item), ['stage' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame(ContentStage::Approved, $item->refresh()->stage);
        $this->assertTrue($item->stage_changed_at->greaterThan($before));

        $this->actingAs($this->member)->patch(route('content.update', $item), ['stage' => 'scheduled'])->assertSessionHasNoErrors();
        $this->actingAs($this->member)->patch(route('content.update', $item), ['stage' => 'preparing'])->assertSessionHasNoErrors();
        $this->assertSame(ContentStage::Preparing, $item->refresh()->stage);
    }

    public function test_editors_and_managers_are_notified_when_content_enters_review()
    {
        Notification::fake();
        $item = $this->item('preparing');

        $this->actingAs($this->member)->patch(route('content.update', $item), ['stage' => 'review']);

        Notification::assertSentTo([$this->editor, $this->manager], ContentAwaitingReview::class);
        Notification::assertNotSentTo($this->member, ContentAwaitingReview::class);
    }

    public function test_the_board_hides_archived_content_unless_asked()
    {
        $this->item('idea', ['title' => 'Ativo']);
        $this->item('archived', ['title' => 'Arquivado']);

        $this->actingAs($this->member)->get(route('content.index'))
            ->assertInertia(fn (Assert $page) => $page->component('content/index')->has('items', 1)->where('can.approve', false));

        $this->actingAs($this->editor)->get(route('content.index', ['archived' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('items', 2)->where('can.approve', true));
    }

    public function test_content_of_other_workspaces_is_not_reachable()
    {
        $item = $this->item();
        $other = Workspace::factory()->create();

        $this->actingAs(User::factory()->inWorkspace($other, WorkspaceRole::Manager)->create())
            ->patch(route('content.update', $item), ['stage' => 'published'])
            ->assertNotFound();
    }
}
