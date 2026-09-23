<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A person of interest (expert) whose profile can be sent to journalists.
 *
 * @property string $id
 * @property string $name
 * @property string|null $academic_title
 * @property string|null $affiliation
 * @property string|null $short_bio
 * @property string|null $bio
 * @property string|null $keywords
 * @property string|null $languages
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $photo_path
 * @property string|null $media_notes
 * @property CarbonImmutable|null $consent_at
 * @property CarbonImmutable|null $last_reviewed_at
 */
#[Fillable(['name', 'academic_title', 'affiliation', 'short_bio', 'bio', 'keywords', 'languages', 'email', 'phone', 'media_notes', 'consent_at', 'last_reviewed_at'])]
class Person extends Model
{
    use IsRecord;

    protected $table = 'people';

    protected function casts(): array
    {
        return [
            'consent_at' => 'date',
            'last_reviewed_at' => 'date',
        ];
    }

    public function recordTitle(): string
    {
        return $this->name;
    }

    /**
     * @return BelongsToMany<ExpertiseArea, $this>
     */
    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(ExpertiseArea::class);
    }

    /**
     * Bios not reviewed for a year may be out of date.
     */
    public function needsReview(): bool
    {
        return $this->last_reviewed_at === null || $this->last_reviewed_at->lt(now()->subYear());
    }
}
