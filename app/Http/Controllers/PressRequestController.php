<?php

namespace App\Http\Controllers;

use App\Core\RecordPage;
use App\Enums\PressRequestStatus;
use App\Http\Requests\PressRequestRequest;
use App\Models\PressRequest;
use App\Models\User;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PressRequestController extends Controller
{
    public function __construct(private WorkspaceContext $context) {}

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['closed', 'all'], true) ? $request->query('status') : 'open';

        $requests = PressRequest::query()
            ->with('responsible:id,name')
            ->when($status === 'open', fn ($query) => $query->whereIn('status', PressRequestStatus::open()))
            ->when($status === 'closed', fn ($query) => $query->whereNotIn('status', PressRequestStatus::open()))
            // Nearest deadline first, requests without deadline last.
            ->orderByRaw('deadline is null')
            ->orderBy('deadline')
            ->orderByDesc('received_at')
            ->get()
            ->map(fn (PressRequest $pressRequest) => $this->summary($pressRequest));

        return Inertia::render('press/index', [
            'requests' => $requests,
            'status' => $status,
            ...$this->formOptions(),
        ]);
    }

    public function store(PressRequestRequest $request): RedirectResponse
    {
        $pressRequest = PressRequest::create([
            ...$request->validated(),
            'received_at' => $request->input('received_at') ?: now(),
            'status' => $request->input('status', PressRequestStatus::Received->value),
        ]);

        return to_route('press.show', $pressRequest);
    }

    public function show(Request $request, PressRequest $press, RecordPage $page): Response
    {
        $press->load('responsible:id,name');

        return Inertia::render('press/show', [
            'pressRequest' => [
                ...$this->summary($press),
                'request' => $press->request,
                'contact' => $press->contact,
                'response_notes' => $press->response_notes,
                'responsible_user_id' => $press->responsible_user_id,
            ],
            ...$this->formOptions(),
            ...$page->for($press->record, $request->user()),
            'can' => ['delete' => $request->user()->can('delete', $press)],
        ]);
    }

    public function update(PressRequestRequest $request, PressRequest $press): RedirectResponse
    {
        $press->update([
            ...$request->validated(),
            ...($request->has('received_at') ? ['received_at' => $request->input('received_at') ?: $press->received_at] : []),
        ]);

        return back();
    }

    public function destroy(PressRequest $press): RedirectResponse
    {
        Gate::authorize('delete', $press);

        $press->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Press request deleted.')]);

        return to_route('press.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(PressRequest $pressRequest): array
    {
        return [
            'id' => $pressRequest->id,
            'subject' => $pressRequest->subject,
            'journalist' => $pressRequest->journalist,
            'media_outlet' => $pressRequest->media_outlet,
            'received_at' => $pressRequest->received_at->toIso8601String(),
            'deadline' => $pressRequest->deadline?->toIso8601String(),
            'status' => $pressRequest->status->value,
            'answered_at' => $pressRequest->answered_at?->toIso8601String(),
            'responsible' => $pressRequest->responsible?->name,
        ];
    }

    /**
     * Team members and the journalists/outlets used before, for autocompletion.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $workspace = $this->context->get() ?? abort(403);

        return [
            'members' => $workspace->members()->orderBy('name')->get(['users.id', 'users.name'])
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]),
            'known' => [
                'journalists' => PressRequest::whereNotNull('journalist')->distinct()->orderBy('journalist')->limit(300)->pluck('journalist'),
                'outlets' => PressRequest::whereNotNull('media_outlet')->distinct()->orderBy('media_outlet')->limit(300)->pluck('media_outlet'),
            ],
        ];
    }
}
