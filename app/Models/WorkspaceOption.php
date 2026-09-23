<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Core\Terms;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One entry of a team's configurable list (an event type, a content format).
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $list
 * @property string $key
 * @property string $label
 * @property string $normalized_label
 * @property string|null $color
 * @property int $position
 * @property bool $active
 */
#[Fillable(['workspace_id', 'list', 'key', 'label', 'color', 'position', 'active'])]
class WorkspaceOption extends Model
{
    use BelongsToWorkspace;

    protected static function booted(): void
    {
        static::saving(function (WorkspaceOption $option) {
            $option->normalized_label = Terms::normalize($option->label);
        });
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }
}
