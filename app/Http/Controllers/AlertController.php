<?php

namespace App\Http\Controllers;

use App\Alerts\Evaluator;
use App\Core\RecordTypes;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, Evaluator $evaluator): Response
    {
        $evaluator->refresh($context->get() ?? abort(403));
        $resolved = $request->query('status') === 'resolved';

        $alerts = Alert::with(['record', 'acknowledgedBy:id,name'])
            ->when(
                $resolved,
                fn ($query) => $query->where('status', AlertStatus::Resolved)->where('resolved_at', '>=', now()->subDays(30))->latest('resolved_at'),
                fn ($query) => $query->where('status', '!=', AlertStatus::Resolved)->orderByRaw("status = 'acknowledged'")->orderByRaw('due_at is null')->orderBy('due_at')->latest(),
            )
            ->limit(200)
            ->get();

        return Inertia::render('alerts/index', [
            'alerts' => $alerts->map(fn (Alert $alert) => self::present($alert)),
            'status' => $resolved ? 'resolved' : 'active',
        ]);
    }

    public function update(Request $request, Alert $alert): RedirectResponse
    {
        $request->validate(['acknowledged' => ['required', 'boolean']]);

        if ($alert->status !== AlertStatus::Resolved) {
            $alert->update($request->boolean('acknowledged')
                ? ['status' => AlertStatus::Acknowledged, 'acknowledged_by' => $request->user()->id]
                : ['status' => AlertStatus::Open, 'acknowledged_by' => null]);
        }

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'message' => $alert->message,
            'title' => $alert->title,
            'severity' => $alert->severity->value,
            'status' => $alert->status->value,
            'due_at' => $alert->due_at?->toIso8601String(),
            'url' => $alert->record !== null ? RecordTypes::url($alert->record) : null,
            'acknowledged_by' => $alert->acknowledgedBy?->name,
            'created_at' => $alert->created_at->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
        ];
    }
}
