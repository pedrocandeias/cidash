<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $kind
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable $generated_at
 * @property array<int, array{key: string, title: string, count: int, items: array<int, array<string, mixed>>}> $content
 * @property string|null $ai_summary
 */
#[Fillable(['workspace_id', 'kind', 'period_start', 'period_end', 'generated_at', 'content', 'ai_summary'])]
class Briefing extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'generated_at' => 'datetime',
            'content' => 'array',
        ];
    }
}
