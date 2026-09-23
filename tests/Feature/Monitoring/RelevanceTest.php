<?php

namespace Tests\Feature\Monitoring;

use App\Enums\SourceKind;
use App\Enums\WorkspaceRole;
use App\Models\MonitoringRule;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Monitoring\Relevance;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RelevanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_score_adds_up_the_teams_signals()
    {
        $this->assertSame(['score' => 0, 'reasons' => []], Relevance::score(false, false, false, 1, now()));
        $this->assertSame(
            ['score' => 10, 'reasons' => ['Matches a monitoring rule', 'About a person of interest', 'Priority source', 'Covered by several outlets']],
            Relevance::score(true, true, true, 6, now()),
        );
        $this->assertSame(5, Relevance::score(true, false, false, 3, now()->subDays(3))['score'], 'older stories lose a point');
    }

    public function test_the_news_inbox_can_be_sorted_by_relevance()
    {
        $rss = fn (string $title, string $link) => "<?xml version=\"1.0\"?><rss version=\"2.0\"><channel><title>F</title><item><title>{$title}</title><link>{$link}</link><pubDate>".now()->toRfc2822String().'</pubDate></item></channel></rss>';
        Http::fake([
            'a.test/*' => Http::response($rss('Maria Silva recebe prémio de investigação', 'https://a.pt/1')),
            'b.test/*' => Http::response($rss('Trânsito condicionado na ponte', 'https://b.pt/1')),
        ]);
        $workspace = Workspace::factory()->create();
        $user = User::factory()->inWorkspace($workspace, WorkspaceRole::Member)->create();
        Source::create(['name' => 'A', 'kind' => SourceKind::Rss, 'url' => 'https://a.test/feed'])->workspaces()->attach($workspace, ['only_matching' => false]);
        Source::create(['name' => 'B', 'kind' => SourceKind::Rss, 'url' => 'https://b.test/feed'])->workspaces()->attach($workspace, ['only_matching' => false, 'is_priority' => true]);
        app(WorkspaceContext::class)->set($workspace);
        $person = Person::create(['name' => 'Maria Silva']);
        MonitoringRule::create(['name' => 'Investigadores', 'include_terms' => ['investigação'], 'person_id' => $person->id, 'google_news' => false]);

        $this->artisan('cidash:fetch-sources');

        $this->actingAs($user)->get(route('news.index', ['sort' => 'relevance']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sort', 'relevance')
                ->where('lines.0.headline', 'Maria Silva recebe prémio de investigação')
                ->where('lines.0.score', 6)
                ->where('lines.1.score', 2)
                ->where('lines.1.reasons', ['Priority source']));
    }
}
