<?php

namespace App\Models;

use App\Enums\SourceKind;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Global source catalogue (super admin). Workspaces subscribe to sources.
 *
 * @property int $id
 * @property string $name
 * @property SourceKind $kind
 * @property string $url
 * @property array<string, mixed>|null $config
 * @property bool $active
 * @property int $poll_minutes
 * @property CarbonImmutable|null $last_fetched_at
 * @property string|null $last_error
 * @property int $consecutive_failures
 */
#[Fillable(['name', 'kind', 'url', 'config', 'active', 'poll_minutes'])]
class Source extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => SourceKind::class,
            'config' => 'array',
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Workspace, $this>
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_sources')->withPivot('is_priority')->withTimestamps();
    }

    public function isDue(): bool
    {
        return $this->last_fetched_at === null || $this->last_fetched_at->addMinutes($this->poll_minutes)->lte(now());
    }
}
