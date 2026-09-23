<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A calendar entry (table `events`; named to avoid Laravel's Event facade).
 * All-day events store whole days: start_at and end_at at 00:00, end inclusive.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property EventType $type
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable|null $end_at
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $organizer
 * @property int|null $responsible_user_id
 * @property Priority $priority
 * @property EventStatus $status
 * @property string|null $notes
 */
#[Fillable(['title', 'description', 'type', 'start_at', 'end_at', 'all_day', 'location', 'organizer', 'responsible_user_id', 'priority', 'status', 'notes'])]
class CalendarEvent extends Model
{
    use IsRecord;

    protected $table = 'events';

    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
            'priority' => Priority::class,
            'status' => EventStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
