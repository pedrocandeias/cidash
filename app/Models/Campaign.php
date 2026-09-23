<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\CampaignStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A communication campaign. Its events and content link to it with part_of.
 *
 * @property string $id
 * @property string $name
 * @property string|null $description
 * @property string|null $objectives
 * @property array<int, string>|null $audiences
 * @property CarbonImmutable|null $start_date
 * @property CarbonImmutable|null $end_date
 * @property array<int, string>|null $channels
 * @property CampaignStatus $status
 */
#[Fillable(['name', 'description', 'objectives', 'audiences', 'start_date', 'end_date', 'channels', 'status'])]
class Campaign extends Model
{
    use IsRecord;

    protected function casts(): array
    {
        return [
            'audiences' => 'array',
            'start_date' => 'date',
            'end_date' => 'date',
            'channels' => 'array',
            'status' => CampaignStatus::class,
        ];
    }

    public function recordTitle(): string
    {
        return $this->name;
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
