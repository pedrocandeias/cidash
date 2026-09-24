<?php

namespace App\Models;

use App\Core\Concerns\IsRecord;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * An image, video or graphic (logo, illustration…) of the team's gallery.
 *
 * @property string $id
 * @property string $title
 * @property string|null $caption
 * @property string|null $credit
 * @property string|null $category
 * @property string $kind image|video|graphic
 * @property CarbonImmutable|null $taken_on
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 */
#[Fillable(['title', 'caption', 'credit', 'category', 'taken_on'])]
class Asset extends Model
{
    use IsRecord;

    /**
     * Attributes indexed for the search, besides the title.
     *
     * @var array<int, string>
     */
    protected array $searchable = ['caption', 'credit', 'original_name'];

    protected static function booted(): void
    {
        static::deleted(function (Asset $asset) {
            Storage::disk('local')->delete(array_filter([$asset->path, $asset->thumbnail_path]));
        });
    }

    protected function casts(): array
    {
        return [
            'taken_on' => 'date',
        ];
    }

    public function recordTitle(): string
    {
        return $this->title;
    }
}
