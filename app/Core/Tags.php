<?php

namespace App\Core;

use App\Models\Record;
use App\Models\Tag;

/**
 * Tags of the current workspace, attached to any record.
 */
class Tags extends Vocabulary
{
    protected function model(): string
    {
        return Tag::class;
    }

    protected function pivotTable(): string
    {
        return 'object_tag';
    }

    protected function termKey(): string
    {
        return 'tag_id';
    }

    protected function ownerKey(): string
    {
        return 'object_id';
    }

    /**
     * @param  array<int, string>  $names
     */
    public function sync(Record $record, array $names): void
    {
        $record->tags()->sync($this->idsFor($names));
    }
}
