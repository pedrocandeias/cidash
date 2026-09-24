<?php

namespace App\Models;

use App\Core\Concerns\HasAssignees;
use App\Core\Concerns\IsRecord;
use App\Enums\ContentStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * An item in the content pipeline (Kanban). Links to campaigns, events and
 * people go through relations.
 *
 * @property string $id
 * @property string $title
 * @property string|null $brief
 * @property string $format key of the team's content formats (App\Support\Options)
 * @property array<int, string>|null $channels
 * @property ContentStage $stage
 * @property CarbonImmutable $stage_changed_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $publish_at
 * @property string|null $published_url
 */
#[Fillable(['title', 'brief', 'format', 'channels', 'stage', 'due_at', 'publish_at', 'published_url'])]
class ContentItem extends Model
{
    use HasAssignees, IsRecord;

    /**
     * Attributes indexed for the global search, besides the title.
     *
     * @var array<int, string>
     */
    protected array $searchable = ['brief'];

    protected static function booted(): void
    {
        static::saving(function (ContentItem $item) {
            if ($item->isDirty('stage') || ! $item->exists) {
                $item->stage_changed_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'stage' => ContentStage::class,
            'stage_changed_at' => 'datetime',
            'due_at' => 'date',
            'publish_at' => 'datetime',
        ];
    }
}
