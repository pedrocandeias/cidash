<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Enums\TriageStatus;
use App\Models\AlertRule;
use App\Models\NewsItemState;
use App\Models\Workspace;

class PrioritySourceItem implements RuleType
{
    public function key(): string
    {
        return 'priority_source_item';
    }

    public function message(): string
    {
        return 'News from a priority source';
    }

    public function description(): string
    {
        return 'A priority source published news in the last :hours hours that is still to triage.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Info;
    }

    public function parameters(): array
    {
        return ['hours' => 24];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $priority = $workspace->sources()->wherePivot('is_priority', true)->pluck('sources.id');

        if ($priority->isEmpty()) {
            return;
        }

        $states = NewsItemState::query()
            ->where('status', TriageStatus::New)
            ->where('created_at', '>=', now()->subHours((int) $rule->param('hours')))
            ->whereHas('newsItem', fn ($query) => $query->whereIn('source_id', $priority))
            ->get();

        foreach ($states as $state) {
            yield new Finding("priority_source_item:{$state->id}", $state->headline, $state->id);
        }
    }
}
