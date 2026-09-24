<?php

namespace App\Models;

use App\Core\Concerns\HasAssignees;
use App\Core\Concerns\IsRecord;
use App\Enums\PressRequestStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $subject
 * @property string|null $request
 * @property string|null $journalist
 * @property string|null $media_outlet
 * @property string|null $contact
 * @property CarbonImmutable $received_at
 * @property CarbonImmutable|null $deadline
 * @property PressRequestStatus $status
 * @property string|null $response_notes
 * @property CarbonImmutable|null $answered_at
 */
#[Fillable(['subject', 'request', 'journalist', 'media_outlet', 'contact', 'received_at', 'deadline', 'status', 'response_notes'])]
class PressRequest extends Model
{
    use HasAssignees, IsRecord;

    /**
     * Attributes indexed for the global search, besides the title.
     *
     * @var array<int, string>
     */
    protected array $searchable = ['request', 'journalist', 'media_outlet', 'response_notes'];

    protected static function booted(): void
    {
        static::saving(function (PressRequest $request) {
            if ($request->isDirty('status')) {
                $request->answered_at = $request->status === PressRequestStatus::Answered ? now() : $request->answered_at;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'deadline' => 'datetime',
            'status' => PressRequestStatus::class,
            'answered_at' => 'datetime',
        ];
    }

    public function recordTitle(): string
    {
        return $this->subject;
    }
}
