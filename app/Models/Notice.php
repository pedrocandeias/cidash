<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Internal notice for the team. Visible from published_at until expires_at.
 *
 * @property string $id
 * @property string $title
 * @property string $body
 * @property CarbonImmutable $published_at
 * @property CarbonImmutable|null $expires_at
 * @property Priority $priority
 * @property bool $pinned
 */
#[Fillable(['title', 'body', 'published_at', 'expires_at', 'priority', 'pinned'])]
class Notice extends Model
{
    use IsRecord;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'priority' => Priority::class,
            'pinned' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('published_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->published_at <= now() && ($this->expires_at === null || $this->expires_at > now());
    }
}
