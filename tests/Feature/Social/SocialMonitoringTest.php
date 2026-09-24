<?php

namespace Tests\Feature\Social;

use App\Enums\WorkspaceRole;
use App\Models\Mention;
use App\Models\Setting;
use App\Models\SocialHashtag;
use App\Models\SocialNetworkState;
use App\Models\User;
use App\Models\Workspace;
use App\Support\SocialSettings;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->workspace = Workspace::factory()->create();
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    private function follow(Workspace $workspace, string $tags): void
    {
        $manager = User::factory()->inWorkspace($workspace, WorkspaceRole::Manager)->create();
        $this->actingAs($manager)->post(route('hashtags.store'), ['tags' => $tags])->assertSessionHasNoErrors();
    }

    private function mastodon(array $statuses): array
    {
        return array_map(fn ($status) => [
            'id' => $status[0], 'url' => "https://mastodon.social/@ana/{$status[0]}", 'created_at' => '2026-09-24T09:00:00Z',
            'content' => "<p>{$status[1]}</p>", 'account' => ['acct' => 'ana@porto.social'],
        ], $statuses);
    }

    public function test_managers_follow_hashtags_typed_in_any_form_and_members_cannot()
    {
        $this->actingAs($this->manager)->post(route('hashtags.store'), ['tags' => '#UPorto, #feup  fep #feup'])->assertSessionHasNoErrors();

        app(WorkspaceContext::class)->set($this->workspace);
        $this->assertSame(['fep', 'feup', 'uporto'], SocialHashtag::orderBy('tag')->pluck('tag')->all());
        $this->actingAs($this->manager)->post(route('hashtags.store'), ['tags' => '#não-vale'])->assertSessionHasErrors();

        $member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        $this->actingAs($member)->post(route('hashtags.store'), ['tags' => '#outra'])->assertForbidden();
        $this->actingAs($this->manager)->get(route('rules.index'))->assertInertia(fn (Assert $page) => $page->has('hashtags', 3));
    }

    public function test_mastodon_posts_become_mentions_of_the_teams_that_follow_the_hashtag()
    {
        Http::fake(['mastodon.social/api/v1/timelines/tag/feup*' => Http::response($this->mastodon([
            ['1', 'Grande dia na <a href="#">#FEUP</a> &amp; no Porto'],
        ])), 'mastodon.social/*' => Http::response([])]);
        $other = Workspace::factory()->create();
        $this->follow($this->workspace, '#feup');
        $this->follow($other, '#fep');

        $this->artisan('cidash:fetch-social')->expectsOutputToContain('mastodon: 1 new mentions')->assertSuccessful();

        app(WorkspaceContext::class)->set($this->workspace);
        $mention = Mention::sole();
        $this->assertSame('mastodon', $mention->network);
        $this->assertSame('@ana@porto.social', $mention->author);
        $this->assertSame('#feup', $mention->matched_keyword);
        $this->assertSame('Grande dia na #FEUP & no Porto', $mention->headline);
        app(WorkspaceContext::class)->set($other);
        $this->assertSame(0, Mention::count());

        $this->actingAs($this->manager)->get(route('mentions.index', ['source' => 'social']))
            ->assertInertia(fn (Assert $page) => $page->has('mentions', 1)->where('mentions.0.network', 'mastodon'));
        $this->actingAs($this->manager)->get(route('mentions.index', ['source' => 'news']))
            ->assertInertia(fn (Assert $page) => $page->has('mentions', 0));
    }

    public function test_the_same_post_under_two_hashtags_is_one_mention_and_runs_are_paced()
    {
        $post = $this->mastodon([['7', '#uporto #feup']]);
        Http::fake(['mastodon.social/*' => Http::response($post)]);
        $this->follow($this->workspace, '#uporto #feup');

        $this->artisan('cidash:fetch-social');
        $this->artisan('cidash:fetch-social')->doesntExpectOutputToContain('mastodon');

        app(WorkspaceContext::class)->set($this->workspace);
        $this->assertSame(1, Mention::count());
    }

    public function test_bluesky_youtube_and_instagram_use_their_apis_with_the_stored_credentials()
    {
        $settings = app(SocialSettings::class);
        $settings->save('bluesky', ['handle' => 'cidash.bsky.social', 'app_password' => 'abcd-efgh']);
        $settings->save('youtube', ['api_key' => 'SECRET-KEY']);
        $settings->save('instagram', ['account_id' => '1784', 'access_token' => 'SECRET-TOKEN']);
        $this->assertStringNotContainsString('SECRET-KEY', json_encode(Setting::find('social')->value));

        Http::fake([
            'mastodon.social/*' => Http::response([]),
            'bsky.social/xrpc/com.atproto.server.createSession' => Http::response(['accessJwt' => 'jwt']),
            'bsky.social/xrpc/app.bsky.feed.searchPosts*' => Http::response(['posts' => [[
                'uri' => 'at://did:plc:x/app.bsky.feed.post/3abc', 'author' => ['handle' => 'rita.bsky.social'],
                'record' => ['text' => 'Na #feup hoje', 'createdAt' => '2026-09-24T08:00:00Z'],
            ]]]),
            'www.googleapis.com/youtube/v3/search*' => Http::response(['items' => [[
                'id' => ['videoId' => 'vid1'], 'snippet' => ['title' => 'Visita à FEUP &#39;26', 'description' => '#feup', 'channelTitle' => 'Canal', 'publishedAt' => '2026-09-23T10:00:00Z'],
            ]]]),
            'graph.facebook.com/v21.0/ig_hashtag_search*' => Http::response(['data' => [['id' => '999']]]),
            'graph.facebook.com/v21.0/999/recent_media*' => Http::response(['data' => [[
                'id' => 'm1', 'caption' => 'Receção #feup', 'permalink' => 'https://www.instagram.com/p/abc/', 'timestamp' => '2026-09-24T07:00:00+0000',
            ]]]),
        ]);
        $this->follow($this->workspace, '#feup');

        $this->artisan('cidash:fetch-social')->assertSuccessful();

        app(WorkspaceContext::class)->set($this->workspace);
        $this->assertEqualsCanonicalizing(['bluesky', 'youtube', 'instagram'], Mention::pluck('network')->all());
        $this->assertSame('https://bsky.app/profile/rita.bsky.social/post/3abc', Mention::where('network', 'bluesky')->value('url'));
        $this->assertStringStartsWith("Visita à FEUP '26", Mention::where('network', 'youtube')->value('excerpt'));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'searchPosts') && $request->hasHeader('Authorization', 'Bearer jwt'));
        $this->assertSame(['feup' => '999'], SocialNetworkState::find('instagram')->state['hashtag_ids']);
    }

    public function test_failures_are_recorded_without_leaking_keys()
    {
        app(SocialSettings::class)->save('youtube', ['api_key' => 'SECRET-KEY']);
        Http::fake([
            'mastodon.social/*' => Http::response([]),
            'www.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid.']], 400),
        ]);
        $this->follow($this->workspace, '#feup');

        $this->artisan('cidash:fetch-social')->expectsOutputToContain('youtube: YouTube: HTTP 400 API key not valid.');

        $state = SocialNetworkState::find('youtube');
        $this->assertSame(1, $state->consecutive_failures);
        $this->assertStringNotContainsString('SECRET-KEY', (string) $state->last_error);
    }

    public function test_only_the_super_admin_sets_up_networks_and_secrets_never_reach_the_page()
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($this->manager)->get(route('admin.social.edit'))->assertForbidden();

        $this->actingAs($admin)->put(route('admin.social.update', 'youtube'), ['api_key' => 'SECRET-KEY'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->put(route('admin.social.update', 'youtube'), ['api_key' => ''])->assertSessionHasNoErrors();
        $this->assertSame('SECRET-KEY', app(SocialSettings::class)->get('youtube')['api_key'], 'an empty secret keeps the stored one');

        $this->actingAs($admin)->get(route('admin.social.edit'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/social')
                ->where('settings.youtube.has_api_key', true)
                ->missing('settings.youtube.api_key')
                ->where('networks.2.configured', true));

        $this->actingAs($admin)->put(route('admin.social.update', 'youtube'), ['clear' => true]);
        $this->assertSame([], app(SocialSettings::class)->get('youtube'));
        $this->actingAs($admin)->put(route('admin.social.update', 'nope'), [])->assertNotFound();
    }
}
