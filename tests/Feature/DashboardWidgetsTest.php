<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\ContentItem;
use App\Models\Notice;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->member = User::factory()->inWorkspace($this->workspace)->create();
    }

    private function seedWorkspace(): void
    {
        app(WorkspaceContext::class)->set($this->workspace);
        $this->actingAs($this->member);

        CalendarEvent::create(['title' => 'Hoje', 'type' => 'institutional', 'start_at' => now()->setTime(15, 0), 'priority' => 'normal', 'status' => 'confirmed']);
        CalendarEvent::create(['title' => 'Daqui a 3 dias', 'type' => 'institutional', 'start_at' => now()->addDays(3), 'priority' => 'normal', 'status' => 'confirmed']);
        CalendarEvent::create(['title' => 'Daqui a um mês', 'type' => 'institutional', 'start_at' => now()->addMonth(), 'priority' => 'normal', 'status' => 'confirmed']);
        Task::create(['title' => 'Minha', 'priority' => 'high', 'status' => 'todo', 'deadline' => now()->subDay()])->syncAssignees([$this->member->id]);
        Task::create(['title' => 'Feita', 'priority' => 'normal', 'status' => 'done'])->syncAssignees([$this->member->id]);
        PressRequest::create(['subject' => 'Urgente', 'received_at' => now(), 'deadline' => now()->addHours(5), 'status' => 'received']);
        ContentItem::create(['title' => 'Em revisão', 'format' => 'news', 'stage' => 'review']);
        Notice::create(['title' => 'Aviso', 'body' => 'x', 'published_at' => now()->subHour(), 'priority' => 'normal', 'pinned' => true]);
    }

    public function test_the_home_page_summarises_the_workspace()
    {
        $this->seedWorkspace();

        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('dashboard')
                ->where('counters.events_today', 1)
                ->where('counters.my_tasks', 1)
                ->where('counters.press_48h', 1)
                ->where('counters.in_review', 1)
                ->has('events', 2)
                ->where('tasks.0.title', 'Minha')
                ->where('press.0.subject', 'Urgente')
                ->where('notices.0.pinned', true)
                ->where('approvals', null)
                ->where('overdue', null));
    }

    public function test_editors_see_approvals_and_managers_see_overdue_tasks()
    {
        $this->seedWorkspace();
        $editor = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Editor)->create();
        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();

        $this->actingAs($editor)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('approvals.0.title', 'Em revisão')->where('overdue', null));

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('approvals', 1)->where('overdue.0.name', $this->member->name)->where('overdue.0.count', 1));
    }

    public function test_other_workspaces_do_not_leak_into_the_home_page()
    {
        $this->seedWorkspace();
        $outsider = User::factory()->inWorkspace()->create();

        $this->actingAs($outsider)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('events', 0)->has('press', 0)->has('notices', 0)->where('counters.in_review', 0));
    }
}
