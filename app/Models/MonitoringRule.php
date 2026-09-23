<?php

namespace App\Models;

use App\Core\Concerns\BelongsToWorkspace;
use App\Core\Terms;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a team monitors: an article matches when it contains one of the include
 * terms (or the linked person's name) and none of the exclude terms, as whole
 * words, ignoring case and accents.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property array<int, string> $include_terms
 * @property array<int, string>|null $exclude_terms
 * @property string|null $person_id
 * @property string|null $category
 * @property bool $google_news
 * @property bool $active
 * @property CarbonImmutable|null $last_fetched_at
 */
#[Fillable(['name', 'include_terms', 'exclude_terms', 'person_id', 'category', 'google_news', 'active'])]
class MonitoringRule extends Model
{
    use BelongsToWorkspace;

    protected function casts(): array
    {
        return [
            'include_terms' => 'array',
            'exclude_terms' => 'array',
            'google_news' => 'boolean',
            'active' => 'boolean',
            'last_fetched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return array<int, string>
     */
    public function terms(): array
    {
        $terms = $this->include_terms;

        if ($this->person_id !== null && ($name = $this->person()->withoutGlobalScopes()->value('name'))) {
            $terms[] = $name;
        }

        return array_values(array_unique(array_filter($terms)));
    }

    /**
     * The first include term found in the text, or null.
     */
    public function match(string $text): ?string
    {
        $haystack = ' '.Terms::normalize(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '').' ';
        $contains = fn (string $term) => ($needle = Terms::normalize(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $term) ?? '')) !== ''
            && str_contains($haystack, ' '.$needle.' ');

        foreach ($this->exclude_terms ?? [] as $term) {
            if ($contains($term)) {
                return null;
            }
        }

        foreach ($this->terms() as $term) {
            if ($contains($term)) {
                return $term;
            }
        }

        return null;
    }

    /**
     * Google News search query: "term1" OR "term2" -"exclude".
     */
    public function googleNewsQuery(): string
    {
        $include = implode(' OR ', array_map(fn ($term) => '"'.$term.'"', $this->terms()));
        $exclude = implode(' ', array_map(fn ($term) => '-"'.$term.'"', $this->exclude_terms ?? []));

        return trim($include.' '.$exclude);
    }
}
