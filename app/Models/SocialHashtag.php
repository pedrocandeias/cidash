<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A hashtag a team follows on social networks (Settings → Monitoring).
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $tag
 */
#[Fillable(['workspace_id', 'tag'])]
class SocialHashtag extends Model
{
    use BelongsToWorkspace;

    /**
     * "#UPorto " → "uporto". Hashtags are case-insensitive; accents are kept, as the networks do.
     */
    public static function normalize(string $tag): string
    {
        return Str::lower(ltrim(trim($tag), '#'));
    }
}
