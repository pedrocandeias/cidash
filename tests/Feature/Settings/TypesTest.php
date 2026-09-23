<?php

namespace Tests\Feature\Settings;

use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceOption;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TypesTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    private function event(string $type): TestResponse
    {
        return $this->actingAs($this->manager)->post(route('events.store'), [
            'title' => 'Evento', 'type' => $type, 'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'all_day' => false, 'priority' => 'normal', 'status' => 'confirmed',
        ]);
    }

    public function test_teams_start_with_the_default_lists_shared_with_every_page()
    {
        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('options.event_type', 5)
                ->where('options.event_type.0', ['key' => 'institutional', 'label' => 'Institutional', 'color' => '#2563eb', 'active' => true])
                ->has('options.content_format', 9));
    }

    public function test_managers_add_rename_recolour_and_switch_off_entries()
    {
        $this->actingAs($this->manager)->post(route('options.store'), ['list' => 'event_type', 'label' => 'Visita guiada', 'color' => '#0ea5e9'])->assertSessionHasNoErrors();
        app(WorkspaceContext::class)->set($this->workspace);
        $visit = WorkspaceOption::where('key', 'visita_guiada')->sole();
        $this->assertSame('#0ea5e9', $visit->color);

        $this->event('visita_guiada')->assertSessionHasNoErrors();
        $this->event('inexistente')->assertSessionHasErrors('type');

        $this->actingAs($this->manager)->patch(route('options.update', $visit->id), ['label' => 'Visita', 'active' => false])->assertSessionHasNoErrors();
        $this->assertSame('visita_guiada', $visit->refresh()->key, 'the key never changes');
        $this->assertSame('visita_guiada', CalendarEvent::sole()->type);
        $this->event('visita_guiada')->assertSessionHasNoErrors();
    }

    public function test_duplicates_are_refused_ignoring_case_and_accents()
    {
        $this->actingAs($this->manager)->get(route('options.index'));

        $this->actingAs($this->manager)->post(route('options.store'), ['list' => 'content_format', 'label' => 'VÍDEO'])->assertSessionHasErrors('label');
        $this->actingAs($this->manager)->post(route('options.store'), ['list' => 'content_format', 'label' => 'notícia'])->assertSessionHasErrors('label');
        $this->actingAs($this->manager)->post(route('options.store'), ['list' => 'event_type', 'label' => 'Vídeo'])->assertSessionHasNoErrors();
    }

    public function test_lists_belong_to_one_team_and_only_managers_change_them()
    {
        $member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        $this->actingAs($this->manager)->post(route('options.store'), ['list' => 'event_type', 'label' => 'Visita guiada']);

        $this->actingAs($member)->get(route('options.index'))->assertForbidden();
        $this->actingAs($member)->post(route('options.store'), ['list' => 'event_type', 'label' => 'Outra'])->assertForbidden();

        $other = Workspace::factory()->create();
        $outsider = User::factory()->inWorkspace($other, WorkspaceRole::Manager)->create();
        $this->actingAs($outsider)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->has('options.event_type', 5));
        $this->actingAs($outsider)->post(route('events.store'), [
            'title' => 'X', 'type' => 'visita_guiada', 'start_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'all_day' => false, 'priority' => 'normal', 'status' => 'confirmed',
        ])->assertSessionHasErrors('type');
    }
}
