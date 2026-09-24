<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Enums\PressRequestStatus;
use App\Models\AlertRule;
use App\Models\PressRequest;
use App\Models\Workspace;

class PressDeadlineNear implements RuleType
{
    public function key(): string
    {
        return 'press_deadline_near';
    }

    public function message(): string
    {
        return 'Press request deadline near';
    }

    public function description(): string
    {
        return 'An open press request is due within :hours hours, or is overdue.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Critical;
    }

    public function parameters(): array
    {
        return ['hours' => 24];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $requests = PressRequest::with('assignees:id')
            ->whereIn('status', PressRequestStatus::open())
            ->whereNotNull('deadline')
            ->where('deadline', '<=', now()->addHours((int) $rule->param('hours')))
            ->get();

        foreach ($requests as $request) {
            $owners = $request->assigneeIds();

            yield new Finding("press_deadline_near:{$request->id}", $request->subject, $request->id, $request->deadline, $owners);
        }
    }
}
