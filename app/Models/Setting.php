<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Instance configuration edited in the Admin area, one row per group (e.g. "mail").
 * Sensitive values inside a group are encrypted by the code that owns the group.
 *
 * @property string $key
 * @property array<string, mixed> $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
