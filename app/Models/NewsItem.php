<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A collected article (public content), stored once for the whole instance.
 * What a team does with it lives in NewsItemState.
 *
 * @property int $id
 * @property int $source_id
 * @property string $url
 * @property string $canonical_url
 * @property string $url_hash
 * @property string $headline
 * @property string|null $summary
 * @property string|null $outlet
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $retrieved_at
 * @property string|null $language
 * @property int|null $story_id
 */
#[Fillable(['source_id', 'url', 'canonical_url', 'url_hash', 'headline', 'summary', 'outlet', 'published_at', 'retrieved_at', 'language', 'story_id'])]
class NewsItem extends Model
{
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'retrieved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Source, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
