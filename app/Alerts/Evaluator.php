<?php

namespace App\Alerts;

use App\Core\Scopes\WorkspaceScope;
use App\Enums\AlertStatus;
use App\Enums\WorkspaceRole;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Membership;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AlertRaised;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Runs a workspace's alert rules: opens an alert for each new finding (and
 * notifies managers and owners), and resolves alerts whose finding is gone.
 */
class Evaluator
{
    public function __construct(private WorkspaceContext $context) {}

    /**
     * Makes sure the workspace has one rule row per catalogue type.
     */
    public function install(Workspace $workspace): void
    {
        foreach (Catalog::all() as $key => $type) {
            AlertRule::withoutGlobalScope(WorkspaceScope::class)->firstOrCreate(
                ['workspace_id' => $workspace->id, 'rule_type' => $key],
                ['severity' => $type->severity(), 'active' => true],
            );
        }
    }

    /**
     * Runs the rules when a page shows alerts, at most once a minute per workspace,
     * so fixed problems disappear without waiting for the scheduler.
     */
    public function refresh(Workspace $workspace): void
    {
        if (Cache::add("alerts:evaluated:{$workspace->id}", true, 60)) {
            $this->run($workspace);
        }
    }

    /**
     * @return int number of new alerts
     */
    public function run(Workspace $workspace): int
    {
        $this->install($workspace);
        $previous = $this->context->get();
        $this->context->set($workspace);

        try {
            $new = 0;
            foreach (AlertRule::all() as $rule) {
                $new += $this->runRule($rule, $workspace);
            }

            return $new;
        } finally {
            if ($previous !== null) {
                $this->context->set($previous);
            }
        }
    }

    private function runRule(AlertRule $rule, Workspace $workspace): int
    {
        $findings = [];
        if ($rule->active) {
            foreach ($rule->type()->evaluate($rule, $workspace) as $finding) {
                $findings[$finding->key] = $finding;
            }
        }

        return DB::transaction(function () use ($rule, $findings) {
            $existing = Alert::where('rule_id', $rule->id)->get()->keyBy('dedupe_key');

            foreach ($existing as $key => $alert) {
                if ($alert->status !== AlertStatus::Resolved && ! isset($findings[$key])) {
                    $alert->update(['status' => AlertStatus::Resolved, 'resolved_at' => now()]);
                }
            }

            $new = 0;
            foreach ($findings as $key => $finding) {
                $alert = $existing->get($key);
                $attributes = ['title' => $finding->title, 'due_at' => $finding->dueAt, 'severity' => $rule->severity];

                if ($alert === null) {
                    $alert = Alert::create([
                        ...$attributes,
                        'rule_id' => $rule->id,
                        'object_id' => $finding->recordId,
                        'message' => $rule->type()->message(),
                        'dedupe_key' => $key,
                        'status' => AlertStatus::Open,
                    ]);
                    $this->notify($alert, $finding);
                    $new++;
                } elseif ($alert->status === AlertStatus::Resolved) {
                    // The problem came back.
                    $alert->update([...$attributes, 'status' => AlertStatus::Open, 'resolved_at' => null, 'acknowledged_by' => null]);
                    $this->notify($alert, $finding);
                    $new++;
                } else {
                    $alert->update($attributes);
                }
            }

            return $new;
        });
    }

    private function notify(Alert $alert, Finding $finding): void
    {
        $managers = Membership::where('workspace_id', $alert->workspace_id)
            ->where('role', WorkspaceRole::Manager)
            ->pluck('user_id')
            ->all();

        User::whereIn('id', array_unique([...$managers, ...$finding->owners]))
            ->whereNull('deactivated_at')
            ->get()
            ->each(fn (User $user) => $user->notify(new AlertRaised($alert)));
    }
}
