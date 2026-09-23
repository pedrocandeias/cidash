<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * News items about the same happening (grouped by similar headlines).
 *
 * @property int $id
 * @property string $title
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 * @property int $item_count
 */
#[Fillable(['title', 'first_seen_at', 'last_seen_at', 'item_count'])]
class Story extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<NewsItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(NewsItem::class);
    }
}
