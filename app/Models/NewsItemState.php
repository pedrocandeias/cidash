<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\TriageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A news item as seen by one workspace (record type "news"): its triage status
 * and relevance, comments, relations and tasks are private to the workspace.
 *
 * @property string $id
 * @property int $workspace_id
 * @property int $news_item_id
 * @property string $headline
 * @property TriageStatus $status
 * @property string|null $relevance
 * @property int|null $reviewed_by
 * @property CarbonImmutable|null $reviewed_at
 */
#[Fillable(['workspace_id', 'news_item_id', 'headline', 'status', 'relevance', 'reviewed_by', 'reviewed_at'])]
class NewsItemState extends Model
{
    use IsRecord;

    /**
     * @var array<int, string>
     */
    protected array $searchable = [];

    protected function casts(): array
    {
        return [
            'status' => TriageStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function recordTitle(): string
    {
        return $this->headline;
    }

    public function searchableText(): string
    {
        return (string) $this->newsItem?->summary;
    }

    /**
     * @return BelongsTo<NewsItem, $this>
     */
    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }
}
