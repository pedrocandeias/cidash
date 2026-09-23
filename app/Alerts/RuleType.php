<?php

namespace App\Alerts;

use App\Enums\AlertSeverity;
use App\Models\AlertRule;
use App\Models\Workspace;

/**
 * A type of the fixed alert catalogue. Evaluated inside the workspace context.
 */
interface RuleType
{
    public function key(): string;

    /**
     * English i18n key shown for each alert, e.g. "Event without an owner".
     */
    public function message(): string;

    public function description(): string;

    public function severity(): AlertSeverity;

    /**
     * Editable numeric parameters with their defaults.
     *
     * @return array<string, int>
     */
    public function parameters(): array;

    /**
     * @return iterable<Finding>
     */
    public function evaluate(AlertRule $rule, Workspace $workspace): iterable;
}
