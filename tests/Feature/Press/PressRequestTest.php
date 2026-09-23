<?php

namespace Tests\Feature\Press;

use App\Enums\PressRequestStatus;
use App\Enums\WorkspaceRole;
use App\Models\PressRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PressRequestTest extends TestCase
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

    private function pressRequest(array $attributes = [], ?Workspace $workspace = null, ?User $creator = null): PressRequest
    {
        app(WorkspaceContext::class)->set($workspace ?? $this->workspace);
        $this->actingAs($creator ?? $this->ana);

        return PressRequest::create(['subject' => 'Pedido', 'received_at' => now(), 'status' => 'received', ...$attributes]);
    }

    public function test_a_request_is_registered_with_defaults()
    {
        $this->actingAs($this->ana)->post(route('press.store'), [
            'subject' => 'Ranking de universidades',
            'journalist' => 'Maria Costa',
            'media_outlet' => 'Público',
            'deadline' => now()->addHours(5)->format('Y-m-d\TH:i'),
            'responsible_user_id' => $this->ana->id,
        ])->assertSessionHasNoErrors();

        $request = PressRequest::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('Ranking de universidades', $request->record->title);
        $this->assertSame(PressRequestStatus::Received, $request->status);
        $this->assertNotNull($request->received_at);
    }

    public function test_open_requests_are_listed_by_deadline_with_known_journalists_and_outlets()
    {
        $this->pressRequest(['subject' => 'Sem prazo', 'journalist' => 'Rita', 'media_outlet' => 'JN']);
        $this->pressRequest(['subject' => 'Amanhã', 'deadline' => now()->addDay(), 'media_outlet' => 'Público']);
        $this->pressRequest(['subject' => 'Hoje', 'deadline' => now()->addHours(3), 'media_outlet' => 'Público']);
        $this->pressRequest(['subject' => 'Respondido', 'status' => 'answered']);

        $this->actingAs($this->ana)->get(route('press.index'))
            ->assertInertia(fn (Assert $page) => $page->component('press/index')
                ->has('requests', 3)
                ->where('requests.0.subject', 'Hoje')
                ->where('requests.2.subject', 'Sem prazo')
                ->where('known.outlets', ['JN', 'Público'])
                ->where('known.journalists', ['Rita']));

        $this->actingAs($this->ana)->get(route('press.index', ['status' => 'closed']))
            ->assertInertia(fn (Assert $page) => $page->has('requests', 1));
    }

    public function test_answering_records_the_answer_date()
    {
        $request = $this->pressRequest();

        $this->actingAs($this->ana)->patch(route('press.update', $request), ['status' => 'answered', 'response_notes' => 'Enviada declaração do Reitor.']);

        $request->refresh();
        $this->assertSame(PressRequestStatus::Answered, $request->status);
        $this->assertNotNull($request->answered_at);
    }

    public function test_the_request_page_includes_the_record_panels()
    {
        $request = $this->pressRequest(['subject' => 'Entrevista RTP']);

        $this->actingAs($this->ana)->get(route('press.show', $request))
            ->assertInertia(fn (Assert $page) => $page->component('press/show')
                ->where('pressRequest.subject', 'Entrevista RTP')
                ->has('comments')->has('relations')->has('reminders')->has('activity'));
    }

    public function test_only_the_creator_or_a_manager_can_delete_and_other_teams_cannot_see_it()
    {
        $request = $this->pressRequest();
        $rui = User::factory()->inWorkspace($this->workspace)->create();
        $this->actingAs($rui)->delete(route('press.destroy', $request))->assertForbidden();

        $other = Workspace::factory()->create();
        $this->actingAs(User::factory()->inWorkspace($other)->create())->get(route('press.show', $request))->assertNotFound();

        $manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->delete(route('press.destroy', $request))->assertRedirect(route('press.index'));
    }
}
