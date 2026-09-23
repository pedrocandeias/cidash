<?php

namespace App\Http\Controllers;

use App\Core\Links;
use App\Core\RecordPage;
use App\Core\RecordTypes;
use App\Enums\CampaignStatus;
use App\Enums\RelationType;
use App\Http\Requests\CampaignRequest;
use App\Models\Campaign;
use App\Models\Link;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $all = $request->query('view') === 'all';

        $campaigns = Campaign::query()
            ->with('responsibles:id,name')
            ->when(! $all, fn ($query) => $query->whereIn('status', [CampaignStatus::Planning, CampaignStatus::Active]))
            ->orderByRaw('start_date is null')
            ->orderBy('start_date')
            ->get();

        // How many records are part of each campaign.
        $counts = Link::whereIn('target_id', $campaigns->modelKeys())
            ->where('type', RelationType::PartOf)
            ->selectRaw('target_id, count(*) as total')
            ->groupBy('target_id')
            ->pluck('total', 'target_id');

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns->map(fn (Campaign $campaign) => [
                ...$this->summary($campaign),
                'items' => (int) ($counts[$campaign->id] ?? 0),
            ]),
            'view' => $all ? 'all' : 'current',
            'members' => $this->members(),
        ]);
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $campaign = DB::transaction(function () use ($request) {
            $campaign = Campaign::create([
                ...$request->safe()->except('responsibles'),
                'status' => $request->input('status', CampaignStatus::Planning->value),
            ]);
            $campaign->responsibles()->sync($request->input('responsibles', []));

            return $campaign;
        });

        return to_route('campaigns.show', $campaign);
    }

    public function show(Request $request, Campaign $campaign, RecordPage $page, Links $links): Response
    {
        $campaign->load('responsibles:id,name');
        $shared = $page->for($campaign->record, $request->user());

        // Records that are part of the campaign are shown in their own section.
        $parts = $links->of($campaign)
            ->filter(fn (array $item) => $item['link']->type === RelationType::PartOf && ! $item['outgoing'])
            ->map(fn (array $item) => ['link_id' => $item['link']->id, 'record' => RecordTypes::summary($item['other'])])
            ->values();

        /** @var Collection<int, array{type: string, outgoing: bool}> $relations */
        $relations = $shared['relations'];

        return Inertia::render('campaigns/show', [
            'campaign' => [
                ...$this->summary($campaign),
                'description' => $campaign->description,
                'objectives' => $campaign->objectives,
            ],
            'parts' => $parts,
            'members' => $this->members(),
            ...$shared,
            'relations' => $relations->reject(fn (array $relation) => $relation['type'] === RelationType::PartOf->value && ! $relation['outgoing'])->values(),
            'can' => ['delete' => $request->user()->can('delete', $campaign)],
        ]);
    }

    public function update(CampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        DB::transaction(function () use ($request, $campaign) {
            $campaign->update($request->safe()->except('responsibles'));

            if ($request->has('responsibles')) {
                $campaign->responsibles()->sync($request->input('responsibles', []));
            }
        });

        return back();
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        Gate::authorize('delete', $campaign);

        $campaign->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign deleted.')]);

        return to_route('campaigns.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Campaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'start_date' => $campaign->start_date?->toDateString(),
            'end_date' => $campaign->end_date?->toDateString(),
            'audiences' => $campaign->audiences ?? [],
            'channels' => $campaign->channels ?? [],
            'responsibles' => $campaign->responsibles->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])->values(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function members(): array
    {
        $workspace = $this->context->get() ?? abort(403);

        return $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
            ->all();
    }
}
