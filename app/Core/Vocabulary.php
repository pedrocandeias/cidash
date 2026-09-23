<?php

namespace App\Core;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A user-created list of terms of the current workspace (tags, expertise areas),
 * never duplicated by case, accents or spacing (see Terms). The term model has
 * `name` and `normalized_name` and is attached to its owners through a pivot table.
 */
abstract class Vocabulary
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    abstract protected function pivotTable(): string;

    /** Pivot column pointing at the term. */
    abstract protected function termKey(): string;

    /** Pivot column pointing at the owner (record, person…). */
    abstract protected function ownerKey(): string;

    /**
     * The existing term with the same normalized name, or a new one keeping the
     * spelling it was first written with.
     */
    public function findOrCreate(string $name): Model
    {
        $normalized = Terms::normalize($name);
        $model = $this->model();

        try {
            return $model::firstOrCreate(['normalized_name' => $normalized], ['name' => Terms::clean($name)]);
        } catch (UniqueConstraintViolationException) {
            return $model::where('normalized_name', $normalized)->firstOrFail();
        }
    }

    /**
     * Existing terms that look like a typo of $name ("Queria dizer…?").
     *
     * @return Collection<int, Model>
     */
    public function similarTo(string $name): Collection
    {
        return collect($this->model()::orderBy('name')->get()->all())
            ->filter(fn (Model $term) => Terms::similar($term->getAttribute('name'), $name))
            ->values();
    }

    /**
     * Matches for what is being typed, and near-miss spellings.
     *
     * @return array{matches: Collection<int, string>, similar: Collection<int, string>}
     */
    public function suggest(string $query): array
    {
        return [
            'matches' => $this->model()::where('normalized_name', 'like', '%'.Terms::normalize($query).'%')
                ->orderBy('name')->limit(8)->pluck('name'),
            'similar' => $this->similarTo($query)->pluck('name'),
        ];
    }

    /**
     * Ids of the terms for these names, creating the missing ones.
     *
     * @param  array<int, string>  $names
     * @return Collection<int, mixed>
     */
    public function idsFor(array $names): Collection
    {
        return collect($names)
            ->filter(fn (string $name) => Terms::normalize($name) !== '')
            ->map(fn (string $name) => $this->findOrCreate($name)->getKey())
            ->unique()
            ->values();
    }

    /**
     * Rename a term. If the new name already exists, the term is merged into it.
     *
     * @return Model the term that remains
     */
    public function rename(Model $term, string $name): Model
    {
        $normalized = Terms::normalize($name);

        if ($normalized === '') {
            throw new InvalidArgumentException('A term cannot be empty.');
        }

        $existing = $this->model()::where('normalized_name', $normalized)->whereKeyNot($term->getKey())->first();

        if ($existing !== null) {
            $this->merge($term, $existing);

            return $existing;
        }

        $term->forceFill(['name' => Terms::clean($name), 'normalized_name' => $normalized])->save();

        return $term;
    }

    /**
     * Move every use of $from to $into and delete $from.
     */
    public function merge(Model $from, Model $into): void
    {
        if ($from->is($into)) {
            return;
        }

        DB::transaction(function () use ($from, $into) {
            $pivot = DB::table($this->pivotTable());
            $already = (clone $pivot)->where($this->termKey(), $into->getKey())->pluck($this->ownerKey());

            (clone $pivot)->where($this->termKey(), $from->getKey())
                ->whereNotIn($this->ownerKey(), $already)
                ->update([$this->termKey() => $into->getKey()]);

            $from->delete();
        });
    }

    public function usage(Model $term): int
    {
        return DB::table($this->pivotTable())->where($this->termKey(), $term->getKey())->count();
    }
}
