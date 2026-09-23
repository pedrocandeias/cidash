<?php

namespace Tests\Feature\Admin;

use App\Enums\SourceKind;
use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\NewsItemState;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WorkspacesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_super_admin' => true]);
    }

    public function test_the_super_admin_sees_every_team_at_a_glance()
    {
        $reitoria = Workspace::factory()->create(['name' => 'CI Reitoria']);
        $manager = User::factory()->inWorkspace($reitoria, WorkspaceRole::Manager)->create(['name' => 'Ana Reis']);
        User::factory()->inWorkspace($reitoria, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($reitoria);
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now(), 'priority' => 'normal', 'status' => 'confirmed']);
        Workspace::factory()->create(['name' => 'CI FEUP']);

        $this->actingAs($this->admin)->get(route('admin.workspaces.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/workspaces')
                ->has('workspaces', 2)
                ->where('workspaces.1.name', 'CI Reitoria')
                ->where('workspaces.1.members', 2)
                ->where('workspaces.1.records', 1)
                ->where('workspaces.1.managers', ['Ana Reis'])
                ->where('workspaces.0.managers', [])
                ->has('ingestion.items_24h'));

        $this->actingAs($manager)->get(route('admin.workspaces.index'))->assertForbidden();
    }

    public function test_creating_renaming_and_naming_a_manager()
    {
        $this->actingAs($this->admin)->post(route('admin.workspaces.store'), ['name' => 'CI FEUP'])->assertSessionHasNoErrors();
        $feup = Workspace::where('slug', 'ci-feup')->sole();
        $this->actingAs($this->admin)->post(route('admin.workspaces.store'), ['name' => 'CI FEUP'])->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->patch(route('admin.workspaces.update', $feup), ['name' => 'Comunicação FEUP'])->assertSessionHasNoErrors();
        $this->assertSame('Comunicação FEUP', $feup->refresh()->name);

        $this->actingAs($this->admin)->post(route('admin.workspaces.managers.store', $feup), ['name' => 'Rui Lima', 'email' => 'rui@fe.up.pt'])->assertSessionHasNoErrors();
        $rui = User::where('email', 'rui@fe.up.pt')->sole();
        $this->assertSame(WorkspaceRole::Manager, $rui->roleIn($feup));
    }

    public function test_an_archived_team_stops_working()
    {
        $archived = Workspace::factory()->create();
        $active = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($archived, WorkspaceRole::Manager)->create();
        $solo = User::factory()->inWorkspace($archived, WorkspaceRole::Member)->create();
        $active->members()->attach($user, ['role' => WorkspaceRole::Member]);
        Http::fake(['feeds.test/*' => Http::response('<?xml version="1.0"?><rss version="2.0"><channel><title>F</title><item><title>Notícia</title><link>https://a.pt/1</link></item></channel></rss>')]);
        Source::create(['name' => 'A', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/a'])->workspaces()->attach($archived, ['only_matching' => false]);

        $this->actingAs($this->admin)->patch(route('admin.workspaces.update', $archived), ['archived' => true])->assertSessionHasNoErrors();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('workspace.id', $active->id)->has('workspaces', 1));
        $this->actingAs($user)->post(route('workspaces.switch', $archived))->assertForbidden();
        $this->actingAs($solo)->get(route('dashboard'))->assertForbidden();

        $this->artisan('cidash:fetch-sources');
        $this->assertSame(0, NewsItemState::withoutGlobalScopes()->count());
        $this->artisan('cidash:generate-briefings')->doesntExpectOutputToContain($archived->name);
    }

    public function test_people_in_several_teams_switch_between_them()
    {
        $reitoria = Workspace::factory()->create(['name' => 'CI Reitoria']);
        $feup = Workspace::factory()->create(['name' => 'CI FEUP']);
        $other = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($reitoria, WorkspaceRole::Member)->create();
        $feup->members()->attach($user, ['role' => WorkspaceRole::Manager]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('workspace.id', $reitoria->id)->where('workspaces', [['id' => $feup->id, 'name' => 'CI FEUP'], ['id' => $reitoria->id, 'name' => 'CI Reitoria']]));

        $this->actingAs($user)->post(route('workspaces.switch', $feup))->assertRedirect(route('dashboard'));
        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('workspace.id', $feup->id)->where('workspace.role', 'manager'));

        $this->actingAs($user)->post(route('workspaces.switch', $other))->assertForbidden();
        $this->actingAs($this->admin)->post(route('workspaces.switch', $other))->assertRedirect();
    }
}
