<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a rule found wrong. Open until someone acknowledges it; resolved
 * automatically when the rule no longer finds it.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $rule_id
 * @property string|null $object_id
 * @property AlertSeverity $severity
 * @property string $message
 * @property string $title
 * @property CarbonImmutable|null $due_at
 * @property string $dedupe_key
 * @property AlertStatus $status
 * @property int|null $acknowledged_by
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['workspace_id', 'rule_id', 'object_id', 'severity', 'message', 'title', 'due_at', 'dedupe_key', 'status', 'acknowledged_by', 'resolved_at'])]
class Alert extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => AlertStatus::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AlertRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class);
    }

    /**
     * @return BelongsTo<Record, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class, 'object_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
