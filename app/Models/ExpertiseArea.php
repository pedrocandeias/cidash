<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property string $normalized_name
 */
#[Fillable(['name', 'normalized_name'])]
class ExpertiseArea extends Model
{
    use BelongsToWorkspace;
}
