<?php

namespace Tests\Feature\Campaigns;

use App\Enums\RelationType;
use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampaignTest extends TestCase
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

    private function in(): void
    {
        app(WorkspaceContext::class)->set($this->workspace);
        $this->actingAs($this->ana);
    }

    public function test_a_campaign_is_created_with_audiences_channels_and_responsibles()
    {
        $this->actingAs($this->ana)->post(route('campaigns.store'), [
            'name' => 'Candidaturas 2027',
            'audiences' => 'futuros estudantes, famílias, , futuros estudantes',
            'channels' => ['instagram', 'website'],
            'responsibles' => [$this->ana->id],
            'start_date' => '2027-01-10',
            'end_date' => '2027-03-31',
        ])->assertSessionHasNoErrors();

        $campaign = Campaign::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['futuros estudantes', 'famílias'], $campaign->audiences);
        $this->assertSame(['instagram', 'website'], $campaign->channels);
        $this->assertSame(['Ana'], $campaign->responsibles->pluck('name')->all());
        $this->assertSame('Candidaturas 2027', $campaign->record->title);
    }

    public function test_events_and_content_are_added_to_a_campaign_as_parts()
    {
        $this->in();
        $campaign = Campaign::create(['name' => 'Candidaturas 2027', 'status' => 'active']);
        $event = CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'campaign', 'start_at' => now(), 'priority' => 'normal', 'status' => 'confirmed']);

        $this->actingAs($this->ana)->post(route('links.store', $campaign->id), ['target_id' => $event->id, 'type' => 'part_of', 'reverse' => true])->assertSessionHasNoErrors();

        $this->assertTrue(Link::where('source_id', $event->id)->where('target_id', $campaign->id)->where('type', RelationType::PartOf)->exists());

        $this->actingAs($this->ana)->get(route('campaigns.show', $campaign))
            ->assertInertia(fn (Assert $page) => $page->component('campaigns/show')
                ->where('parts.0.record.title', 'Dia Aberto')
                ->has('relations', 0));

        $this->actingAs($this->ana)->get(route('campaigns.index'))
            ->assertInertia(fn (Assert $page) => $page->where('campaigns.0.items', 1));
    }

    public function test_the_list_shows_planned_and_active_campaigns_by_default()
    {
        $this->in();
        Campaign::create(['name' => 'Ativa', 'status' => 'active']);
        Campaign::create(['name' => 'Terminada', 'status' => 'finished']);

        $this->actingAs($this->ana)->get(route('campaigns.index'))->assertInertia(fn (Assert $page) => $page->has('campaigns', 1));
        $this->actingAs($this->ana)->get(route('campaigns.index', ['view' => 'all']))->assertInertia(fn (Assert $page) => $page->has('campaigns', 2));
    }

    public function test_only_members_can_be_responsible_and_other_teams_cannot_see_it()
    {
        $outsider = User::factory()->inWorkspace()->create();

        $this->actingAs($this->ana)->post(route('campaigns.store'), ['name' => 'X', 'responsibles' => [$outsider->id]])->assertSessionHasErrors('responsibles.0');

        $this->in();
        $campaign = Campaign::create(['name' => 'Nossa', 'status' => 'planning']);
        $this->actingAs($outsider)->get(route('campaigns.show', $campaign))->assertNotFound();

        $rui = User::factory()->inWorkspace($this->workspace)->create();
        $this->actingAs($rui)->delete(route('campaigns.destroy', $campaign))->assertForbidden();
        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->delete(route('campaigns.destroy', $campaign))->assertRedirect(route('campaigns.index'));
    }
}
