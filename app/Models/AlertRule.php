<?php

namespace App\Models;

use App\Alerts\Catalog;
use App\Alerts\RuleType;
use App\Core\Concerns\BelongsToWorkspace;
use App\Enums\AlertSeverity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A team's settings for one type of the fixed alert catalogue (ARCHITECTURE.md §3).
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $rule_type
 * @property array<string, int|string>|null $params
 * @property AlertSeverity $severity
 * @property bool $active
 */
#[Fillable(['workspace_id', 'rule_type', 'params', 'severity', 'active'])]
class AlertRule extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'params' => 'array',
            'severity' => AlertSeverity::class,
            'active' => 'boolean',
        ];
    }

    public function type(): RuleType
    {
        return Catalog::get($this->rule_type);
    }

    /**
     * The saved value of a parameter, or the catalogue default.
     */
    public function param(string $name): int|string
    {
        return $this->params[$name] ?? $this->type()->parameters()[$name];
    }
}
