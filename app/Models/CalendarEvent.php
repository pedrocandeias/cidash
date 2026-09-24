<?php

namespace App\Models;

use App\Core\Concerns\HasAssignees;
use App\Core\Concerns\IsRecord;
use App\Enums\EventStatus;
use App\Enums\Priority;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A calendar entry (table `events`; named to avoid Laravel's Event facade).
 * All-day events store whole days: start_at and end_at at 00:00, end inclusive.
 *
 * @property string $id
 * @property string $title
 * @property string|null $description
 * @property string $type key of the team's event types (App\Support\Options)
 * @property CarbonImmutable $start_at
 * @property CarbonImmutable|null $end_at
 * @property bool $all_day
 * @property string|null $location
 * @property string|null $organizer
 * @property Priority $priority
 * @property EventStatus $status
 * @property string|null $notes
 */
#[Fillable(['title', 'description', 'type', 'start_at', 'end_at', 'all_day', 'location', 'organizer', 'priority', 'status', 'notes'])]
class CalendarEvent extends Model
{
    use HasAssignees, IsRecord;

    /**
     * Attributes indexed for the global search, besides the title.
     *
     * @var array<int, string>
     */
    protected array $searchable = ['description', 'location', 'organizer', 'notes'];

    protected $table = 'events';

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'all_day' => 'boolean',
            'priority' => Priority::class,
            'status' => EventStatus::class,
        ];
    }
}
