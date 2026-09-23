<?php

namespace Tests\Feature;

use App\Enums\SourceKind;
use App\Enums\WorkspaceRole;
use App\Models\ExpertiseArea;
use App\Models\MonitoringRule;
use App\Models\Person;
use App\Models\Source;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Workspace::factory()->create();
        $this->manager = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Manager)->create();
    }

    public function test_managers_of_a_new_team_see_the_first_steps_until_done()
    {
        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('onboarding', 5)->where('onboarding.0.done', false));

        User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();
        Source::create(['name' => 'JN', 'kind' => SourceKind::Rss, 'url' => 'https://jn.test/rss'])->workspaces()->attach($this->workspace);
        app(WorkspaceContext::class)->set($this->workspace);
        MonitoringRule::create(['name' => 'FEUP', 'include_terms' => ['FEUP'], 'google_news' => false]);

        $this->actingAs($this->manager)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('onboarding.0.done', true)->where('onboarding.2.done', true)->where('onboarding.3.done', false));

        Person::create(['name' => 'Maria Silva']);
        ExpertiseArea::create(['name' => 'Astronomia', 'normalized_name' => 'astronomia']);

        $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
    }

    public function test_managers_can_hide_it_and_members_never_see_it()
    {
        $member = User::factory()->inWorkspace($this->workspace, WorkspaceRole::Member)->create();

        $this->actingAs($member)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
        $this->actingAs($member)->post(route('onboarding.dismiss'))->assertForbidden();

        $this->actingAs($this->manager)->post(route('onboarding.dismiss'))->assertRedirect();
        $this->actingAs($this->manager)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('onboarding', null));
    }

    public function test_the_guide_is_rendered_in_the_users_language()
    {
        $this->actingAs($this->manager)->get(route('guide'))
            ->assertInertia(fn (Assert $page) => $page->component('guide')
                ->where('html', fn (string $html) => str_contains($html, '<h1>Guia do CIDASH</h1>') && str_contains($html, 'Primeiros passos')));
    }
}
