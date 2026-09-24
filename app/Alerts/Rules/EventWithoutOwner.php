<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Enums\EventStatus;
use App\Models\AlertRule;
use App\Models\CalendarEvent;
use App\Models\Workspace;

class EventWithoutOwner implements RuleType
{
    public function key(): string
    {
        return 'event_without_owner';
    }

    public function message(): string
    {
        return 'Event without an owner';
    }

    public function description(): string
    {
        return 'An event starts within :hours hours and nobody is responsible for it.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Warning;
    }

    public function parameters(): array
    {
        return ['hours' => 48];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $events = CalendarEvent::query()
            ->unassigned()
            ->whereIn('status', [EventStatus::Tentative, EventStatus::Confirmed])
            ->where('start_at', '>=', now()->startOfDay())
            ->where('start_at', '<=', now()->addHours((int) $rule->param('hours')))
            ->get();

        foreach ($events as $event) {
            yield new Finding("event_without_owner:{$event->id}", $event->title, $event->id, $event->start_at);
        }
    }
}
