<?php

namespace App\Core;

use App\Enums\RelationType;
use App\Models\Link;
use App\Models\Record;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

/**
 * Typed links between records of the same workspace, shown in both directions.
 */
class Links
{
    public function link(Model $from, Model $to, RelationType $type = RelationType::RelatedTo, ?string $note = null): Link
    {
        $source = $this->record($from);
        $target = $this->record($to);

        if ($source->workspace_id !== $target->workspace_id) {
            throw new InvalidArgumentException('Records from different workspaces cannot be linked.');
        }

        if ($source->is($target)) {
            throw new InvalidArgumentException('A record cannot be linked to itself.');
        }

        return Link::firstOrCreate(
            ['source_id' => $source->id, 'target_id' => $target->id, 'type' => $type],
            ['note' => $note, 'created_by' => auth()->id()],
        );
    }

    public function unlink(Model $from, Model $to, RelationType $type = RelationType::RelatedTo): void
    {
        Link::where('source_id', $this->record($from)->id)
            ->where('target_id', $this->record($to)->id)
            ->where('type', $type)
            ->delete();
    }

    /**
     * Links of a record in both directions, each with the record on the other end.
     *
     * @return Collection<int, array{link: Link, other: Record, outgoing: bool}>
     */
    public function of(Model $model): Collection
    {
        $id = $this->record($model)->id;

        return Link::with(['source', 'target'])
            ->where(fn ($query) => $query->where('source_id', $id)->orWhere('target_id', $id))
            ->get()
            ->map(fn (Link $link) => [
                'link' => $link,
                'other' => ($link->source_id === $id ? $link->target : $link->source)
                    ?? throw new LogicException('Linked record not found.'),
                'outgoing' => $link->source_id === $id,
            ]);
    }

    private function record(Model $model): Record
    {
        if ($model instanceof Record) {
            return $model;
        }

        /** @var Record|null $record */
        $record = $model->getAttribute('record');

        return $record ?? throw new InvalidArgumentException($model::class.' is not a record.');
    }
}
