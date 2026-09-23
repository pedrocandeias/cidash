<?php

namespace Tests\Feature\Notices;

use App\Enums\WorkspaceRole;
use App\Models\Notice;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NoticeTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace)->create(['name' => 'Rui']);
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    private function notice(array $attributes = [], ?User $author = null, ?Workspace $workspace = null): Notice
    {
        app(WorkspaceContext::class)->set($workspace ?? $this->workspace);
        $this->actingAs($author ?? $this->member);

        return Notice::create(['title' => 'Aviso', 'body' => 'Texto', 'published_at' => now()->subHour(), 'priority' => 'normal', ...$attributes]);
    }

    public function test_active_notices_are_listed_pinned_first()
    {
        $this->notice(['title' => 'Antigo', 'published_at' => now()->subDays(2)]);
        $this->notice(['title' => 'Fixado', 'published_at' => now()->subDays(5), 'pinned' => true]);
        $this->notice(['title' => 'Agendado', 'published_at' => now()->addDay()]);
        $this->notice(['title' => 'Expirado', 'expires_at' => now()->subMinute()]);

        $this->actingAs($this->member)->get(route('notices.index'))
            ->assertInertia(fn (Assert $page) => $page->component('notices/index')
                ->has('notices', 2)
                ->where('notices.0.title', 'Fixado')
                ->where('notices.1.author', 'Rui'));

        $this->actingAs($this->member)->get(route('notices.index', ['view' => 'archive']))
            ->assertInertia(fn (Assert $page) => $page->has('notices', 2));
    }

    public function test_members_publish_notices_but_only_managers_pin_them()
    {
        $this->actingAs($this->member)->post(route('notices.store'), ['title' => 'Fixar?', 'body' => 'x', 'pinned' => true])->assertForbidden();

        $this->actingAs($this->member)->post(route('notices.store'), ['title' => 'Fecho do edifício', 'body' => 'Sexta-feira'])->assertSessionHasNoErrors();
        $notice = Notice::withoutGlobalScopes()->where('title', 'Fecho do edifício')->firstOrFail();
        $this->assertFalse($notice->pinned);
        $this->assertTrue($notice->isActive());

        $this->actingAs($this->member)->patch(route('notices.update', $notice), ['pinned' => true])->assertForbidden();
        $this->actingAs($this->manager)->patch(route('notices.update', $notice), ['pinned' => true]);
        $this->assertTrue($notice->refresh()->pinned);

        $this->actingAs($this->member)->patch(route('notices.update', $notice), ['title' => 'Fecho do edifício (sexta)', 'pinned' => true])->assertSessionHasNoErrors();
    }

    public function test_the_expiry_must_follow_the_publication()
    {
        $this->actingAs($this->member)->post(route('notices.store'), [
            'title' => 'x', 'body' => 'x', 'published_at' => '2026-10-10T10:00', 'expires_at' => '2026-10-09T10:00',
        ])->assertSessionHasErrors('expires_at');
    }

    public function test_only_the_author_or_a_manager_can_delete_and_other_teams_cannot_see_it()
    {
        $notice = $this->notice([], $this->manager);

        $this->actingAs($this->member)->delete(route('notices.destroy', $notice))->assertForbidden();

        $other = Workspace::factory()->create();
        $outsider = User::factory()->inWorkspace($other)->create();
        $this->actingAs($outsider)->get(route('notices.show', $notice))->assertNotFound();

        $this->actingAs($this->manager)->delete(route('notices.destroy', $notice))->assertRedirect(route('notices.index'));
    }
}
