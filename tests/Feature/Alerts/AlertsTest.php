<?php

namespace Tests\Feature\Alerts;

use App\Alerts\Evaluator;
use App\Core\Links;
use App\Enums\AlertStatus;
use App\Enums\RelationType;
use App\Enums\SourceKind;
use App\Enums\WorkspaceRole;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\PressRequest;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AlertRaised;
use App\Notifications\SourceFailingNotification;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AlertsTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $manager;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
        $this->member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        app(WorkspaceContext::class)->set($this->workspace);
    }

    private function evaluate(): int
    {
        return app(Evaluator::class)->run($this->workspace);
    }

    /**
     * @return array<int, string>
     */
    private function open(): array
    {
        return Alert::where('status', '!=', AlertStatus::Resolved)->orderBy('id')->pluck('title')->all();
    }

    public function test_each_catalogue_rule_finds_its_problem()
    {
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed']);
        CalendarEvent::create(['title' => 'Daqui a um mês', 'type' => 'institutional', 'start_at' => now()->addMonth(), 'priority' => 'normal', 'status' => 'confirmed']);
        CalendarEvent::create(['title' => 'Com dono', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed', 'responsible_user_id' => $this->member->id]);
        PressRequest::create(['subject' => 'Público ranking', 'received_at' => now(), 'deadline' => now()->addHours(5), 'status' => 'received']);
        PressRequest::create(['subject' => 'Sem pressa', 'received_at' => now(), 'deadline' => now()->addDays(5), 'status' => 'received']);
        $stuck = ContentItem::create(['title' => 'Prémio Reitoria', 'format' => 'news', 'stage' => 'review']);
        $stuck->forceFill(['stage_changed_at' => now()->subDays(5)])->save();
        ContentItem::create(['title' => 'Acabado de entrar', 'format' => 'news', 'stage' => 'review']);
        Campaign::create(['name' => 'Candidaturas', 'status' => 'planning', 'start_date' => now()->addDays(3)->toDateString()]);
        $covered = Campaign::create(['name' => 'Com conteúdos', 'status' => 'planning', 'start_date' => now()->addDays(3)->toDateString()]);
        app(Links::class)->link(ContentItem::create(['title' => 'Peça', 'format' => 'news', 'stage' => 'idea']), $covered, RelationType::PartOf);

        $source = Source::create(['name' => 'Público', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/p']);
        $source->forceFill(['consecutive_failures' => 4])->save();
        $source->workspaces()->attach($this->workspace, ['is_priority' => true]);
        $item = NewsItem::create(['source_id' => $source->id, 'url' => 'https://p.pt/1', 'canonical_url' => 'https://p.pt/1', 'url_hash' => sha1('1'), 'headline' => 'Notícia prioritária', 'outlet' => 'Público', 'retrieved_at' => now()]);
        NewsItemState::create(['workspace_id' => $this->workspace->id, 'news_item_id' => $item->id, 'headline' => $item->headline, 'status' => 'new']);

        $this->assertSame(6, $this->evaluate());
        $this->assertEqualsCanonicalizing(['Dia Aberto', 'Público ranking', 'Prémio Reitoria', 'Candidaturas', 'Notícia prioritária', 'Público'], $this->open());
        $this->assertSame(0, $this->evaluate(), 'the same problems are not alerted twice');
    }

    public function test_alerts_resolve_when_fixed_notify_managers_and_owners_and_come_back()
    {
        Notification::fake();
        $request = PressRequest::create(['subject' => 'RTP entrevista', 'received_at' => now(), 'deadline' => now()->addHours(3), 'status' => 'received', 'responsible_user_id' => $this->member->id]);

        $this->evaluate();
        Notification::assertSentTo([$this->manager, $this->member], AlertRaised::class);

        $request->update(['status' => 'answered']);
        $this->evaluate();
        $alert = Alert::sole();
        $this->assertSame(AlertStatus::Resolved, $alert->status);
        $this->assertNotNull($alert->resolved_at);

        $request->update(['status' => 'in_progress']);
        $this->assertSame(1, $this->evaluate());
        $this->assertSame(AlertStatus::Open, $alert->refresh()->status);
    }

    public function test_rules_can_be_switched_off_and_tuned_by_managers_only()
    {
        CalendarEvent::create(['title' => 'Em 3 dias', 'type' => 'institutional', 'start_at' => now()->addDays(3), 'priority' => 'normal', 'status' => 'confirmed']);
        $this->evaluate();
        $this->assertSame([], $this->open(), 'outside the default 48 hours');

        $rule = AlertRule::where('rule_type', 'event_without_owner')->sole();
        $this->actingAs($this->member)->patch(route('alert-rules.update', $rule->id), ['params' => ['hours' => 96]])->assertForbidden();
        $this->actingAs($this->manager)->patch(route('alert-rules.update', $rule->id), ['params' => ['hours' => 96], 'severity' => 'critical'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->patch(route('alert-rules.update', $rule->id), ['params' => ['nope' => 1]])->assertSessionHasErrors('params');

        $this->evaluate();
        $this->assertSame(['Em 3 dias'], $this->open());
        $this->assertSame('critical', Alert::sole()->severity->value);

        $this->actingAs($this->manager)->patch(route('alert-rules.update', $rule->id), ['active' => false]);
        $this->evaluate();
        $this->assertSame([], $this->open());

        $this->actingAs($this->manager)->get(route('alert-rules.index'))
            ->assertInertia(fn (Assert $page) => $page->component('settings/alerts')->has('rules', 6)->where('rules.0.params.hours', 96));
    }

    public function test_the_alerts_page_and_home_strip_show_open_alerts_of_the_team_only()
    {
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed']);
        $other = Workspace::factory()->create();
        app(WorkspaceContext::class)->set($other);
        CalendarEvent::create(['title' => 'De outra equipa', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed']);
        app(Evaluator::class)->run($other);

        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('alerts.count', 1)->where('alerts.items.0.title', 'Dia Aberto')->where('alerts.items.0.message', 'Event without an owner'));

        app(WorkspaceContext::class)->set($this->workspace);
        $alert = Alert::sole();
        $this->actingAs($this->member)->patch(route('alerts.update', $alert->id), ['acknowledged' => true])->assertRedirect();
        $this->assertSame($this->member->id, $alert->refresh()->acknowledged_by);

        $this->actingAs($this->member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('alerts.count', 0));
        $this->actingAs($this->member)->get(route('alerts.index'))
            ->assertInertia(fn (Assert $page) => $page->component('alerts/index')->has('alerts', 1)->where('alerts.0.acknowledged_by', $this->member->name));

        app(WorkspaceContext::class)->set($other);
        $this->actingAs($this->member)->patch(route('alerts.update', Alert::sole()->id), ['acknowledged' => true])->assertNotFound();
    }

    public function test_super_admins_are_told_once_when_a_source_starts_failing()
    {
        Notification::fake();
        $admin = User::factory()->create(['is_super_admin' => true]);
        Http::fake(['feeds.test/*' => Http::response('down', 500)]);
        Source::create(['name' => 'Falha', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/f']);

        foreach (range(1, 4) as $run) {
            $this->artisan('cidash:fetch-sources', ['--all' => true]);
        }

        Notification::assertSentToTimes($admin, SourceFailingNotification::class, 1);
    }

    public function test_the_scheduled_command_evaluates_every_workspace()
    {
        CalendarEvent::create(['title' => 'Dia Aberto', 'type' => 'institutional', 'start_at' => now()->addDay(), 'priority' => 'normal', 'status' => 'confirmed']);

        $this->artisan('cidash:evaluate-alerts')->expectsOutputToContain("{$this->workspace->name}: 1 new alerts")->assertSuccessful();
    }
}
