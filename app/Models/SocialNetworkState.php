<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $network
 * @property CarbonImmutable|null $last_fetched_at
 * @property string|null $last_error
 * @property int $consecutive_failures
 * @property array<string, mixed>|null $state
 */
#[Fillable(['network', 'last_fetched_at', 'last_error', 'consecutive_failures', 'state'])]
class SocialNetworkState extends Model
{
    protected $primaryKey = 'network';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'last_fetched_at' => 'datetime',
            'state' => 'array',
        ];
    }

    public static function for(string $network): self
    {
        return self::firstOrCreate(['network' => $network]);
    }
}
