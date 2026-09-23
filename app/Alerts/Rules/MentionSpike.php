<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Models\AlertRule;
use App\Models\Mention;
use App\Models\MonitoringRule;
use App\Models\Workspace;

/**
 * A monitoring rule suddenly has many more mentions than usual: compares the
 * last window with the average window of the previous week.
 */
class MentionSpike implements RuleType
{
    public function key(): string
    {
        return 'mention_spike';
    }

    public function message(): string
    {
        return 'Spike in mentions';
    }

    public function description(): string
    {
        return 'A monitoring rule has at least :minimum mentions in :hours hours, :factor times its usual number.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Warning;
    }

    public function parameters(): array
    {
        return ['hours' => 24, 'factor' => 3, 'minimum' => 5];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $hours = (int) $rule->param('hours');
        $windowStart = now()->subHours($hours);
        $baselineStart = $windowStart->subWeek();

        foreach (MonitoringRule::where('active', true)->get() as $monitoring) {
            $recent = Mention::where('rule_id', $monitoring->id)->where('created_at', '>=', $windowStart)->count();
            $before = Mention::where('rule_id', $monitoring->id)->whereBetween('created_at', [$baselineStart, $windowStart])->count();
            $usual = $before / (7 * 24 / $hours);

            if ($recent >= (int) $rule->param('minimum') && $recent >= (int) $rule->param('factor') * max($usual, 1)) {
                yield new Finding(
                    "mention_spike:{$monitoring->id}",
                    "{$monitoring->name} ({$recent})",
                    url: route('mentions.index', absolute: false),
                );
            }
        }
    }
}
