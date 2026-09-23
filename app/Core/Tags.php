<?php

namespace App\Core;

use App\Models\Record;
use App\Models\Tag;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Tags of the current workspace, never duplicated (see Terms).
 */
class Tags
{
    /**
     * The existing tag with the same normalized name, or a new one keeping the
     * spelling it was first written with.
     */
    public function findOrCreate(string $name): Tag
    {
        $normalized = Terms::normalize($name);

        try {
            return Tag::firstOrCreate(['normalized_name' => $normalized], ['name' => Terms::clean($name)]);
        } catch (UniqueConstraintViolationException) {
            return Tag::where('normalized_name', $normalized)->firstOrFail();
        }
    }

    /**
     * Existing tags that look like a typo of $name ("Queria dizer…?").
     *
     * @return Collection<int, Tag>
     */
    public function similarTo(string $name): Collection
    {
        return Tag::orderBy('name')->get()
            ->filter(fn (Tag $tag) => Terms::similar($tag->name, $name))
            ->values();
    }

    /**
     * @param  array<int, string>  $names
     */
    public function sync(Record $record, array $names): void
    {
        $ids = collect($names)
            ->filter(fn (string $name) => Terms::normalize($name) !== '')
            ->map(fn (string $name) => $this->findOrCreate($name)->id)
            ->unique();

        $record->tags()->sync($ids);
    }
}
