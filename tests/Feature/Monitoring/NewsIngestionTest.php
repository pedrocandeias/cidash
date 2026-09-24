<?php

namespace Tests\Feature\Monitoring;

use App\Enums\SourceKind;
use App\Enums\TriageStatus;
use App\Enums\WorkspaceRole;
use App\Models\NewsItem;
use App\Models\NewsItemState;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Monitoring\Robots;
use App\Monitoring\Stories;
use App\Monitoring\Urls;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function rss(array $items): string
    {
        $xml = collect($items)->map(fn ($item) => "<item><title>{$item[0]}</title><link>{$item[1]}</link><description>&lt;p&gt;{$item[2]}&lt;/p&gt;</description><pubDate>Wed, 23 Sep 2026 10:00:00 GMT</pubDate>".(isset($item[3]) ? "<source url=\"https://x\">{$item[3]}</source>" : '').'</item>')->implode('');

        return "<?xml version=\"1.0\"?><rss version=\"2.0\"><channel><title>Feed</title>{$xml}</channel></rss>";
    }

    public function test_urls_are_canonicalized()
    {
        $this->assertSame('https://publico.pt/2026/09/23/noticia', Urls::canonical('https://www.Publico.pt/2026/09/23/noticia/?utm_source=rss&utm_medium=x#top'));
        $this->assertSame('https://jn.pt/a?id=3', Urls::canonical('https://jn.pt/a?fbclid=1&id=3'));
    }

    public function test_articles_are_stored_once_and_reach_only_subscribed_workspaces()
    {
        Http::fake(['feeds.test/*' => Http::response($this->rss([
            ['U.Porto sobe no ranking', 'https://www.publico.pt/ranking?utm_source=rss', 'Resumo do artigo.'],
            ['Outra notícia', 'https://www.publico.pt/outra', 'Outro resumo.'],
        ]))]);

        $subscribed = Workspace::factory()->create();
        $other = Workspace::factory()->create();
        $source = Source::create(['name' => 'Público', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/publico']);
        $source->workspaces()->attach($subscribed);

        $this->artisan('cidash:fetch-sources')->expectsOutputToContain('Público: 2 new')->assertSuccessful();
        $this->artisan('cidash:fetch-sources', ['--all' => true])->expectsOutputToContain('Público: 0 new');

        $item = NewsItem::where('headline', 'U.Porto sobe no ranking')->firstOrFail();
        $this->assertSame('https://publico.pt/ranking', $item->canonical_url);
        $this->assertSame('Resumo do artigo.', $item->summary);
        $this->assertSame('Público', $item->outlet);

        app(WorkspaceContext::class)->set($subscribed);
        $this->assertSame(2, NewsItemState::where('status', TriageStatus::New)->count());
        app(WorkspaceContext::class)->set($other);
        $this->assertSame(0, NewsItemState::count());
    }

    public function test_google_news_titles_lose_the_outlet_and_join_the_same_story()
    {
        Http::fake([
            'feeds.test/*' => Http::response($this->rss([['Novo estudo da Universidade do Porto alerta para sinais de demência', 'https://tvi.pt/estudo', 'x']])),
            'news.test/*' => Http::response($this->rss([['Novo estudo da Universidade do Porto alerta para sinais de demência - CNN Portugal', 'https://news.google.com/rss/articles/abc', 'x', 'CNN Portugal']])),
        ]);
        Source::create(['name' => 'TVI', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/tvi']);
        Source::create(['name' => 'Google News', 'kind' => SourceKind::GoogleNews, 'url' => 'https://news.test/search']);

        $this->artisan('cidash:fetch-sources');

        $google = NewsItem::where('outlet', 'CNN Portugal')->firstOrFail();
        $this->assertSame('Novo estudo da Universidade do Porto alerta para sinais de demência', $google->headline);
        $this->assertSame(NewsItem::where('outlet', 'TVI')->value('story_id'), $google->story_id);
        $this->assertGreaterThan(0.9, Stories::similarity('Nova cantina da U.Porto', 'Nova cantina da U.Porto'));
        $this->assertLessThan(0.3, Stories::similarity('Nova cantina da U.Porto', 'Ranking de universidades europeias'));
    }

    public function test_failures_are_counted_and_cleared()
    {
        Http::fake(['feeds.test/*' => Http::sequence()->push('down', 500)->push('down', 500)->push('down', 500)->push($this->rss([['Ok', 'https://x.pt/ok', 'y']]))]);
        $source = Source::create(['name' => 'Falha', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/f']);

        $this->artisan('cidash:fetch-sources');
        $this->assertSame(1, $source->refresh()->consecutive_failures);
        $this->assertStringContainsString('500', (string) $source->last_error);

        $this->artisan('cidash:fetch-sources', ['--all' => true]);
        $this->assertSame(0, $source->refresh()->consecutive_failures);
        $this->assertNull($source->last_error);
    }

    public function test_robots_rules_are_parsed()
    {
        $rules = Robots::disallowedPaths("User-agent: GPTBot\nDisallow: /\n\nUser-agent: *\nDisallow: /admin\nDisallow: /premium # paywall\n");

        $this->assertSame(['/admin', '/premium'], $rules);
    }

    public function test_the_inbox_groups_by_story_and_triage_applies_to_the_whole_story()
    {
        Http::fake([
            'a.test/*' => Http::response($this->rss([['U.Porto recebe prémio europeu de inovação', 'https://a.pt/1', 'x']])),
            'b.test/*' => Http::response($this->rss([['U.Porto recebe prémio europeu de inovação', 'https://b.pt/1', 'x']])),
        ]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Manager)->create();
        foreach (['a', 'b'] as $name) {
            Source::create(['name' => strtoupper($name), 'kind' => SourceKind::Rss, 'url' => "https://{$name}.test/feed"])->workspaces()->attach($workspace);
        }
        $this->artisan('cidash:fetch-sources');

        $this->actingAs($user)->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page->component('news/index')->has('lines', 1)->where('lines.0.story_count', 2));

        app(WorkspaceContext::class)->set($workspace);
        $first = NewsItemState::firstOrFail();
        $this->actingAs($user)->patch(route('news.update', $first), ['status' => 'relevant', 'whole_story' => true]);

        app(WorkspaceContext::class)->set($workspace);
        $this->assertSame(2, NewsItemState::where('status', TriageStatus::Relevant)->count());
        $this->actingAs($user)->get(route('news.index'))->assertInertia(fn (Assert $page) => $page->has('lines', 0));
    }

    public function test_only_super_admins_manage_the_catalogue_and_managers_subscribe()
    {
        $workspace = Workspace::factory()->create();
        $manager = User::factory()->inWorkspace($workspace, WorkspaceRole::Manager)->create();
        $member = User::factory()->inWorkspace($workspace)->create();
        $source = Source::create(['name' => 'JN', 'kind' => SourceKind::Rss, 'url' => 'https://feeds.test/jn']);

        $this->actingAs($manager)->post(route('admin.sources.store'), ['name' => 'X', 'kind' => 'rss', 'url' => 'https://x.pt/rss'])->assertForbidden();
        $this->actingAs($member)->patch(route('subscriptions.update', $source), ['subscribed' => true])->assertForbidden();

        $this->actingAs($manager)->patch(route('subscriptions.update', $source), ['subscribed' => true, 'is_priority' => true]);
        $this->assertTrue((bool) $workspace->sources()->first()->pivot->is_priority);
    }

    public function test_aggregators_name_the_original_outlet_of_each_item()
    {
        $xml = '<?xml version="1.0"?><rss version="2.0"><channel><title>SAPO</title>'
            .'<item><title>Notícia da SIC</title><link>https://sapo.pt/artigo/a</link><author>SIC Notícias/Rita Lopes</author></item>'
            .'<item><title>Sem autor</title><link>https://sapo.pt/artigo/b</link></item>'
            .'</channel></rss>';
        Http::fake(['sapo.test/*' => Http::response($xml)]);
        Source::create(['name' => 'SAPO Notícias', 'kind' => SourceKind::Rss, 'url' => 'https://sapo.test/rss', 'config' => ['outlet_from_author' => true]]);

        $this->artisan('cidash:fetch-sources');

        $this->assertSame('SIC Notícias', NewsItem::where('headline', 'Notícia da SIC')->value('outlet'));
        $this->assertSame('SAPO Notícias', NewsItem::where('headline', 'Sem autor')->value('outlet'));
    }
}
