<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use App\Monitoring\FeedParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_knows_the_version_of_the_latest_release()
    {
        preg_match('/^## (\d+\.\d+\.\d+)/m', (string) file_get_contents(base_path('CHANGELOG.md')), $match);
        $user = User::factory()->inWorkspace(Workspace::factory()->create(), WorkspaceRole::Member)->create();

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('version', $match[1]));
        auth()->logout();
        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('version', $match[1]));
    }

    public function test_feed_titles_lose_invisible_characters()
    {
        $this->assertSame('Avaliação bancária', FeedParser::text("\u{200B}Avaliação\u{FEFF} bancária"));
    }
}
