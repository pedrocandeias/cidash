<?php

namespace App\Support;

use App\Models\ExpertiseArea;
use App\Models\MonitoringRule;
use App\Models\Person;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * "First steps" for a new team (e.g. a faculty's communication office joining):
 * what its managers should set up before the team starts using CIDASH.
 */
class Onboarding
{
    /**
     * The checklist, or null once it is done or dismissed. Runs in the workspace context.
     *
     * @return array<int, array{key: string, label: string, description: string, done: bool, url: string}>|null
     */
    public function steps(Workspace $workspace): ?array
    {
        if ($workspace->onboarding_dismissed_at !== null) {
            return null;
        }

        $steps = [
            [
                'key' => 'members',
                'label' => 'Add the team',
                'description' => 'Invite the people who work in communication.',
                'done' => DB::table('workspace_user')->where('workspace_id', $workspace->id)->count() > 1,
                'url' => route('team.index', absolute: false),
            ],
            [
                'key' => 'sources',
                'label' => 'Choose the news sources',
                'description' => 'The outlets whose news reach the team, and the priority ones.',
                'done' => $workspace->sources()->exists(),
                'url' => route('subscriptions.index', absolute: false),
            ],
            [
                'key' => 'rules',
                'label' => 'Create monitoring rules',
                'description' => 'The names to follow in the news: the faculty, its centres, its leaders.',
                'done' => MonitoringRule::exists(),
                'url' => route('rules.index', absolute: false),
            ],
            [
                'key' => 'people',
                'label' => 'Add people of interest',
                'description' => 'Experts for journalists, with their areas of expertise.',
                'done' => Person::exists() && ExpertiseArea::exists(),
                'url' => route('people.index', absolute: false),
            ],
            [
                'key' => 'guide',
                'label' => 'Read the guide',
                'description' => 'How the team works in CIDASH, in ten minutes.',
                'done' => false,
                'url' => route('guide', absolute: false),
            ],
        ];

        // The guide cannot be ticked; the list ends when everything else is done.
        $pending = array_filter($steps, fn (array $step) => ! $step['done'] && $step['key'] !== 'guide');

        return $pending === [] ? null : $steps;
    }
}
