<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Free label, unique per workspace by its normalized name (see App\Core\Terms).
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $normalized_name
 */
#[Fillable(['name', 'normalized_name'])]
class Tag extends Model
{
    use BelongsToWorkspace;
}
