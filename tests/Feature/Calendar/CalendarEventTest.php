<?php

namespace Tests\Feature\Calendar;

use App\Core\Links;
use App\Enums\RelationType;
use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CalendarEventTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $ana;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->ana = User::factory()->inWorkspace($this->workspace)->create(['name' => 'Ana']);
    }

    private function event(array $attributes = [], ?Workspace $workspace = null, ?User $creator = null): CalendarEvent
    {
        app(WorkspaceContext::class)->set($workspace ?? $this->workspace);
        $this->actingAs($creator ?? $this->ana);

        return CalendarEvent::create([
            'title' => 'Evento', 'type' => 'institutional', 'start_at' => '2026-10-05 10:00',
            'priority' => 'normal', 'status' => 'confirmed', ...$attributes,
        ]);
    }

    public function test_an_event_is_created_with_tags()
    {
        $this->actingAs($this->ana)->post(route('events.store'), [
            'title' => 'Dia Aberto',
            'type' => 'institutional',
            'start_at' => '2026-10-10T09:30',
            'end_at' => '2026-10-10T17:00',
            'location' => 'Reitoria',
            'assignees' => [$this->ana->id],
            'tags' => ['Estudantes', 'estudantes', 'Candidaturas'],
        ])->assertSessionHasNoErrors();

        $event = CalendarEvent::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('2026-10-10 09:30', $event->start_at->format('Y-m-d H:i'));
        $this->assertSame(['Candidaturas', 'Estudantes'], $event->record->tags()->orderBy('name')->pluck('name')->all());
    }

    public function test_all_day_events_are_stored_as_whole_days_and_fed_with_exclusive_ends()
    {
        $this->actingAs($this->ana)->post(route('events.store'), [
            'title' => 'Semana da Ciência', 'type' => 'campaign', 'all_day' => true,
            'start_at' => '2026-10-12', 'end_at' => '2026-10-16',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->ana)
            ->getJson(route('events.feed', ['start' => '2026-10-01T00:00:00+01:00', 'end' => '2026-11-01T00:00:00+00:00']))
            ->assertOk()
            ->assertJsonPath('0.allDay', true)
            ->assertJsonPath('0.start', '2026-10-12')
            ->assertJsonPath('0.end', '2026-10-17');
    }

    public function test_the_feed_returns_only_overlapping_events_of_the_workspace()
    {
        $this->event(['title' => 'Em outubro', 'start_at' => '2026-10-05 10:00']);
        $this->event(['title' => 'Em novembro', 'start_at' => '2026-11-05 10:00']);
        $this->event(['title' => 'Começa antes', 'start_at' => '2026-09-28 10:00', 'end_at' => '2026-10-02 10:00']);
        $this->event(['title' => 'Outra equipa', 'start_at' => '2026-10-06 10:00'], Workspace::factory()->create());

        $titles = $this->actingAs($this->ana)
            ->getJson(route('events.feed', ['start' => '2026-10-01', 'end' => '2026-11-01']))
            ->json('*.title');

        $this->assertEqualsCanonicalizing(['Em outubro', 'Começa antes'], $titles);
    }

    public function test_the_event_page_shows_details_and_updates_tags()
    {
        $event = $this->event(['title' => 'Conferência de imprensa']);

        $this->actingAs($this->ana)->patch(route('events.update', $event), ['tags' => ['Imprensa']])->assertSessionHasNoErrors();

        $this->actingAs($this->ana)->get(route('events.show', $event))
            ->assertInertia(fn (Assert $page) => $page->component('events/show')
                ->where('event.title', 'Conferência de imprensa')
                ->where('event.tags', ['Imprensa'])
                ->has('relations')
                ->has('reminders'));
    }

    public function test_the_feed_filters_by_type_responsible_and_campaign()
    {
        $rui = User::factory()->inWorkspace($this->workspace)->create();
        $inCampaign = $this->event(['title' => 'Dia Aberto', 'type' => 'campaign', 'start_at' => '2026-10-05 10:00']);
        $campaign = Campaign::create(['name' => 'Candidaturas', 'status' => 'active']);
        app(Links::class)->link($inCampaign, $campaign, RelationType::PartOf);
        $this->event(['title' => 'Do Rui', 'start_at' => '2026-10-06 10:00'])->syncAssignees([$rui->id]);
        $this->event(['title' => 'Efeméride', 'type' => 'ephemeris', 'start_at' => '2026-10-07 00:00']);

        $feed = fn (array $filters) => $this->actingAs($this->ana)->getJson(route('events.feed', ['start' => '2026-10-01', 'end' => '2026-11-01', ...$filters]))->json('*.title');

        $this->assertSame(['Efeméride'], $feed(['types' => ['ephemeris']]));
        $this->assertSame(['Do Rui'], $feed(['responsible' => $rui->id]));
        $this->assertSame(['Dia Aberto'], $feed(['campaign' => $campaign->id]));
    }

    public function test_times_dragged_in_the_calendar_are_stored_in_the_app_time_zone()
    {
        $event = $this->event(['start_at' => '2026-10-05 10:00']);

        $this->actingAs($this->ana)->patch(route('events.update', $event), [
            'start_at' => '2026-10-06T09:00:00.000Z', 'end_at' => '2026-10-06T10:30:00.000Z', 'all_day' => false,
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('2026-10-06 10:00', $event->start_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-06 11:30', $event->end_at->format('Y-m-d H:i'));
    }

    public function test_an_end_before_the_start_is_rejected()
    {
        $this->actingAs($this->ana)->post(route('events.store'), [
            'title' => 'X', 'type' => 'institutional', 'start_at' => '2026-10-10T10:00', 'end_at' => '2026-10-10T09:00',
        ])->assertSessionHasErrors('end_at');
    }

    public function test_only_the_creator_or_a_manager_can_delete_an_event()
    {
        $event = $this->event();
        $rui = User::factory()->inWorkspace($this->workspace)->create();

        $this->actingAs($rui)->delete(route('events.destroy', $event))->assertForbidden();

        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->delete(route('events.destroy', $event))->assertRedirect(route('events.index'));
    }

    public function test_events_of_other_workspaces_are_not_reachable()
    {
        $other = Workspace::factory()->create();
        $theirs = $this->event(['title' => 'Deles'], $other, User::factory()->inWorkspace($other)->create());

        $this->actingAs($this->ana)->get(route('events.show', $theirs))->assertNotFound();
        $this->actingAs($this->ana)->patch(route('events.update', $theirs), ['title' => 'x'])->assertNotFound();
    }
}
