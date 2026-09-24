<?php

namespace App\Models;

use App\Core\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $person_id
 * @property string $path
 */
#[Fillable(['person_id', 'path'])]
class PersonPhoto extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new WorkspaceScope('person_id'));

        static::deleted(fn (PersonPhoto $photo) => Storage::disk('local')->delete($photo->path));
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
