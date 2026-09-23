<?php

namespace App\Alerts\Rules;

use App\Alerts\Finding;
use App\Alerts\RuleType;
use App\Enums\AlertSeverity;
use App\Models\AlertRule;
use App\Models\Source;
use App\Models\Workspace;

/**
 * For the team; super admins are also notified by the ingestor, since they fix sources.
 */
class SourceFailing implements RuleType
{
    public const DEFAULT_FAILURES = 3;

    public function key(): string
    {
        return 'source_failing';
    }

    public function message(): string
    {
        return 'Source failing';
    }

    public function description(): string
    {
        return 'A subscribed source failed :failures times in a row.';
    }

    public function severity(): AlertSeverity
    {
        return AlertSeverity::Info;
    }

    public function parameters(): array
    {
        return ['failures' => self::DEFAULT_FAILURES];
    }

    public function evaluate(AlertRule $rule, Workspace $workspace): iterable
    {
        $sources = $workspace->sources()
            ->where('consecutive_failures', '>=', (int) $rule->param('failures'))
            ->get();

        foreach ($sources as $source) {
            /** @var Source $source */
            yield new Finding("source_failing:{$source->id}", $source->name);
        }
    }
}
