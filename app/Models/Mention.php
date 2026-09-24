<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\TriageStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An article that matched one of the workspace's monitoring rules.
 *
 * @property string $id
 * @property int $workspace_id
 * @property int|null $news_item_id
 * @property int|null $rule_id
 * @property string $url
 * @property string $url_hash
 * @property string $headline
 * @property string|null $excerpt
 * @property string|null $outlet
 * @property CarbonImmutable|null $published_at
 * @property string $matched_keyword
 * @property string|null $category
 * @property string|null $relevance
 * @property TriageStatus $review_status
 * @property string|null $network social network key, null for news
 * @property string|null $author
 */
#[Fillable(['workspace_id', 'news_item_id', 'rule_id', 'url', 'url_hash', 'headline', 'excerpt', 'outlet', 'published_at', 'matched_keyword', 'category', 'relevance', 'review_status', 'network', 'author'])]
class Mention extends Model
{
    use IsRecord;

    /**
     * @var array<int, string>
     */
    protected array $searchable = ['excerpt', 'outlet', 'matched_keyword'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'review_status' => TriageStatus::class,
        ];
    }

    public function recordTitle(): string
    {
        return $this->headline;
    }

    /**
     * @return BelongsTo<MonitoringRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(MonitoringRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<NewsItem, $this>
     */
    public function newsItem(): BelongsTo
    {
        return $this->belongsTo(NewsItem::class);
    }
}
