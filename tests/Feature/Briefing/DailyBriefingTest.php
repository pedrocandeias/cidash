<?php

namespace Tests\Feature\Briefing;

use App\Briefing\DailyBriefing;
use App\Enums\SourceKind;
use App\Enums\TriageStatus;
use App\Enums\WorkspaceRole;
use App\Models\Briefing;
use App\Models\CalendarEvent;
use App\Models\Mention;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\PressRequest;
use App\Models\Source;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DailyBriefingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->user = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($this->workspace);
    }

    /**
     * @return array<string, array{key: string, title: string, count: int, items: array<int, array<string, mixed>>}>
     */
    private function sections(Briefing $briefing): array
    {
        return collect($briefing->content)->keyBy('key')->all();
    }

    private function news(string $headline, TriageStatus $status = TriageStatus::New): NewsItemState
    {
        $source = Source::firstOrCreate(['name' => 'JN'], ['kind' => SourceKind::Rss, 'url' => 'https://feeds.test/jn']);
        $item = NewsItem::create(['source_id' => $source->id, 'url' => "https://jn.pt/{$headline}", 'canonical_url' => "https://jn.pt/{$headline}", 'url_hash' => sha1($headline), 'headline' => $headline, 'outlet' => 'JN', 'retrieved_at' => now()]);

        return NewsItemState::create(['workspace_id' => $this->workspace->id, 'news_item_id' => $item->id, 'headline' => $headline, 'status' => $status]);
    }

    public function test_the_briefing_lists_the_day_and_what_the_news_said()
    {
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->setTime(10, 0), 'priority' => 'normal', 'status' => 'confirmed', 'location' => 'Reitoria']);
        CalendarEvent::create(['title' => 'Amanhã', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed']);
        PressRequest::create(['subject' => 'Público ranking', 'received_at' => now(), 'deadline' => now()->addDay()->setTime(12, 0), 'status' => 'received', 'media_outlet' => 'Público']);
        PressRequest::create(['subject' => 'Para a semana', 'received_at' => now(), 'deadline' => now()->addWeek(), 'status' => 'received']);
        Task::create(['title' => 'Atrasada', 'deadline' => now()->subDays(2)->toDateString(), 'priority' => 'normal', 'status' => 'todo']);
        $this->news('Nova cantina');
        $this->news('Ranking europeu', TriageStatus::Relevant);
        $this->news('Não interessa', TriageStatus::Irrelevant);
        Mention::create(['workspace_id' => $this->workspace->id, 'url' => 'https://a.pt/1', 'url_hash' => sha1('m'), 'headline' => 'U.Porto no topo', 'outlet' => 'DN', 'matched_keyword' => 'U.Porto', 'review_status' => TriageStatus::New]);

        $sections = $this->sections(app(DailyBriefing::class)->generate($this->workspace));

        $this->assertSame(['alerts', 'events', 'press', 'tasks', 'content', 'news', 'mentions', 'notices'], array_keys($sections));
        $this->assertSame(['Dia Aberto'], array_column($sections['events']['items'], 'title'));
        $this->assertSame('Reitoria', $sections['events']['items'][0]['detail']);
        $this->assertSame(['Público ranking'], array_column($sections['press']['items'], 'title'));
        $this->assertTrue($sections['tasks']['items'][0]['flag'], 'overdue tasks are flagged');
        $this->assertSame(['Ranking europeu', 'Nova cantina'], array_column($sections['news']['items'], 'title'), 'relevant news first, irrelevant left out');
        $this->assertSame('DN · U.Porto', $sections['mentions']['items'][0]['detail']);
    }

    public function test_briefings_are_snapshots_and_today_is_generated_on_demand()
    {
        $this->actingAs($this->user)->get(route('briefings.today'))->assertRedirect();
        $briefing = Briefing::sole();
        $this->assertTrue($briefing->period_start->isToday());

        CalendarEvent::create(['title' => 'Marcado depois', 'type' => 'institutional', 'start_at' => now()->setTime(16, 0), 'priority' => 'normal', 'status' => 'confirmed']);
        $this->actingAs($this->user)->get(route('briefings.show', $briefing->id))
            ->assertInertia(fn (Assert $page) => $page->component('briefings/show')->has('briefing.sections.1.items', 0));

        $this->actingAs($this->user)->post(route('briefings.refresh', $briefing->id))->assertRedirect();
        $this->assertSame('Marcado depois', $this->sections($briefing->refresh())['events']['items'][0]['title']);

        $old = Briefing::create(['kind' => 'daily', 'period_start' => now()->subDay()->toDateString(), 'period_end' => now()->subDay()->toDateString(), 'generated_at' => now()->subDay(), 'content' => []]);
        $this->actingAs($this->user)->post(route('briefings.refresh', $old->id))->assertForbidden();
        $this->actingAs($this->user)->get(route('briefings.index'))->assertInertia(fn (Assert $page) => $page->has('briefings', 2));
    }

    public function test_news_since_the_previous_briefing_and_isolation()
    {
        $this->news('Da semana passada')->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->news('De sexta')->forceFill(['created_at' => now()->subDays(3)])->save();
        Briefing::create(['kind' => 'daily', 'period_start' => now()->subDays(3)->toDateString(), 'period_end' => now()->subDays(3)->toDateString(), 'generated_at' => now()->subDays(3)->subHour(), 'content' => []]);

        $sections = $this->sections(app(DailyBriefing::class)->generate($this->workspace));
        $this->assertSame(['De sexta'], array_column($sections['news']['items'], 'title'));

        $other = Workspace::factory()->create();
        $outsider = User::factory()->inWorkspace($other, WorkspaceRole::Member)->create();
        $this->actingAs($outsider)->get(route('briefings.show', Briefing::latest('id')->first()->id))->assertNotFound();
    }

    public function test_the_scheduled_command_generates_every_workspace()
    {
        Workspace::factory()->create();

        $this->artisan('cidash:generate-briefings')->assertSuccessful();

        $this->assertSame(2, Briefing::withoutGlobalScopes()->count());
    }
}
