<?php

namespace Tests\Feature\Monitoring;

use App\Enums\RelationType;
use App\Enums\SourceKind;
use App\Enums\TriageStatus;
use App\Enums\WorkspaceRole;
use App\Models\Link;
use App\Models\Mention;
use App\Models\MonitoringRule;
use App\Models\NewsItemState;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MentionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array<int, string>>  $items  headline, link and, for Google News, the outlet
     */
    private function rss(array $items): string
    {
        $xml = collect($items)->map(fn ($item) => "<item><title>{$item[0]}</title><link>{$item[1]}</link><description>Resumo.</description><pubDate>Wed, 23 Sep 2026 10:00:00 GMT</pubDate>".(isset($item[2]) ? "<source url=\"https://x\">{$item[2]}</source>" : '').'</item>')->implode('');

        return "<?xml version=\"1.0\"?><rss version=\"2.0\"><channel><title>Feed</title>{$xml}</channel></rss>";
    }

    private function rule(Workspace $workspace, array $attributes): MonitoringRule
    {
        app(WorkspaceContext::class)->set($workspace);

        return MonitoringRule::create([...['name' => 'Regra', 'google_news' => false], ...$attributes]);
    }

    public function test_rules_match_whole_words_ignoring_case_and_accents_and_honour_exclusions()
    {
        $rule = new MonitoringRule(['include_terms' => ['Universidade do Porto', 'U.Porto', 'FEUP'], 'exclude_terms' => ['futebol']]);

        $this->assertSame('U.Porto', $rule->match('Investigadores da u.porto descobrem nova espécie'));
        $this->assertSame('Universidade do Porto', $rule->match('A UNIVERSIDADE DO PORTO abre candidaturas'));
        $this->assertNull($rule->match('FEUPIANOS em festa'));
        $this->assertNull($rule->match('Clube da FEUP vence torneio de futebol'));
        $this->assertSame('"Universidade do Porto" OR "U.Porto" OR "FEUP" -"futebol"', $rule->googleNewsQuery());
    }

    public function test_only_matching_subscriptions_keep_the_inbox_to_the_rules_and_create_mentions()
    {
        Http::fake(['feeds.test/*' => Http::response($this->rss([
            ['Reitor da Universidade do Porto fala sobre financiamento', 'https://jn.pt/reitor'],
            ['Trânsito condicionado na VCI', 'https://jn.pt/vci'],
        ]))]);
        $filtered = Workspace::factory()->create();
        $everything = Workspace::factory()->create();
        $source = Source::create(['name' => 'JN', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/jn']);
        $source->workspaces()->attach($filtered, ['only_matching' => true]);
        $source->workspaces()->attach($everything, ['only_matching' => false]);
        $this->rule($filtered, ['include_terms' => ['Universidade do Porto']]);
        $this->rule($everything, ['include_terms' => ['VCI']]);

        $this->artisan('cidash:fetch-sources')->assertSuccessful();

        app(WorkspaceContext::class)->set($filtered);
        $this->assertSame(['Reitor da Universidade do Porto fala sobre financiamento'], NewsItemState::pluck('headline')->all());
        $this->assertSame('Universidade do Porto', Mention::sole()->matched_keyword);

        app(WorkspaceContext::class)->set($everything);
        $this->assertSame(2, NewsItemState::count());
        $this->assertSame('Trânsito condicionado na VCI', Mention::sole()->headline);
    }

    public function test_a_team_without_rules_still_receives_everything()
    {
        Http::fake(['feeds.test/*' => Http::response($this->rss([['Qualquer notícia', 'https://jn.pt/x']]))]);
        $workspace = Workspace::factory()->create();
        Source::create(['name' => 'JN', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/jn'])->workspaces()->attach($workspace);

        $this->artisan('cidash:fetch-sources');

        app(WorkspaceContext::class)->set($workspace);
        $this->assertSame(1, NewsItemState::count());
        $this->assertSame(0, Mention::count());
    }

    public function test_a_rule_with_a_person_matches_the_name_and_links_the_mention()
    {
        Http::fake(['feeds.test/*' => Http::response($this->rss([['Maria Antónia Silva ganha bolsa europeia', 'https://dn.pt/bolsa']]))]);
        $workspace = Workspace::factory()->create();
        Source::create(['name' => 'DN', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/dn'])->workspaces()->attach($workspace);
        app(WorkspaceContext::class)->set($workspace);
        $person = Person::create(['name' => 'Maria Antónia Silva']);
        $this->rule($workspace, ['include_terms' => ['i3S'], 'person_id' => $person->id]);

        $this->artisan('cidash:fetch-sources');

        $mention = Mention::sole();
        $this->assertSame('Maria Antónia Silva', $mention->matched_keyword);
        $this->assertTrue(Link::where(['source_id' => $mention->id, 'target_id' => $person->id, 'type' => RelationType::Mentions])->exists());
    }

    public function test_rules_run_their_own_google_news_search_for_their_team_only()
    {
        Http::fake(['news.google.com/*' => Http::response($this->rss([['Estudo da FEUP sobre baterias - Expresso', 'https://news.google.com/rss/articles/xyz', 'Expresso']]))]);
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $rule = $this->rule($workspace, ['include_terms' => ['FEUP'], 'google_news' => true]);

        $this->artisan('cidash:fetch-sources')->expectsOutputToContain('Rule Regra: 1 new mentions')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'q="FEUP"'));
        $this->assertNotNull($rule->refresh()->last_fetched_at);
        app(WorkspaceContext::class)->set($workspace);
        $this->assertSame('Expresso', Mention::sole()->outlet);
        app(WorkspaceContext::class)->set($other);
        $this->assertSame(0, Mention::count());

        // Not due again for 30 minutes; the internal source stays out of the team's subscriptions.
        $this->artisan('cidash:fetch-sources')->doesntExpectOutputToContain('Rule Regra');
        $manager = User::factory()->inWorkspace($workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->get(route('subscriptions.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sources', 0));
    }

    public function test_the_mentions_inbox_triages_and_is_isolated()
    {
        $workspace = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Member)->create();
        $rule = $this->rule($workspace, ['include_terms' => ['U.Porto']]);
        $mention = Mention::create([
            'workspace_id' => $workspace->id, 'rule_id' => $rule->id, 'url' => 'https://a.pt/1', 'url_hash' => sha1('a'),
            'headline' => 'U.Porto no topo', 'outlet' => 'Público', 'matched_keyword' => 'U.Porto', 'review_status' => TriageStatus::New,
        ]);
        app(WorkspaceContext::class)->set($other);
        $foreign = Mention::create([
            'workspace_id' => $other->id, 'url' => 'https://a.pt/2', 'url_hash' => sha1('b'),
            'headline' => 'De outra equipa', 'matched_keyword' => 'x', 'review_status' => TriageStatus::New,
        ]);

        $this->actingAs($user)->get(route('mentions.index'))
            ->assertInertia(fn (Assert $page) => $page->component('mentions/index')->has('mentions', 1)->where('mentions.0.rule', 'Regra'));
        $this->actingAs($user)->get(route('mentions.show', $mention->id))->assertOk();
        $this->actingAs($user)->get(route('mentions.show', $foreign->id))->assertNotFound();

        $this->actingAs($user)->patch(route('mentions.update', $mention->id), ['review_status' => 'relevant', 'relevance' => 'high'])->assertRedirect();
        $this->actingAs($user)->get(route('mentions.index'))->assertInertia(fn (Assert $page) => $page->has('mentions', 0));
        $this->actingAs($user)->get(route('mentions.index', ['status' => 'relevant']))->assertInertia(fn (Assert $page) => $page->has('mentions', 1));
    }

    public function test_only_managers_manage_rules_and_terms_are_split_by_commas()
    {
        $workspace = Workspace::factory()->create();
        $member = User::factory()->inWorkspace($workspace, WorkspaceRole::Member)->create();
        $manager = User::factory()->inWorkspace($workspace, WorkspaceRole::Manager)->create();

        $this->actingAs($member)->get(route('rules.index'))->assertForbidden();
        $this->actingAs($member)->post(route('rules.store'), ['name' => 'X', 'include_terms' => 'a'])->assertForbidden();

        $this->actingAs($manager)->post(route('rules.store'), [
            'name' => 'Universidade', 'include_terms' => 'Universidade do Porto, U.Porto, ,U.Porto', 'exclude_terms' => '', 'google_news' => true,
        ])->assertSessionHasNoErrors();

        app(WorkspaceContext::class)->set($workspace);
        $rule = MonitoringRule::sole();
        $this->assertSame(['Universidade do Porto', 'U.Porto'], $rule->include_terms);
        $this->assertSame([], $rule->exclude_terms);

        $this->actingAs($manager)->patch(route('rules.update', $rule->id), ['active' => false])->assertSessionHasNoErrors();
        $this->assertFalse($rule->refresh()->active);
        $this->actingAs($manager)->get(route('rules.index'))->assertInertia(fn (Assert $page) => $page->component('settings/monitoring')->has('rules', 1));
        $this->actingAs($manager)->delete(route('rules.destroy', $rule->id));
        $this->assertSame(0, MonitoringRule::count());
    }
}
