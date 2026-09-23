<?php

namespace App\Core\Search;

use App\Models\Record;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SQLite FTS5 implementation: accent- and case-insensitive, prefix matching
 * ("invest" finds "investigação"), ranked with bm25 (title weighted higher).
 */
class SqliteSearch implements Search
{
    public function index(Record $record, string $body): void
    {
        DB::transaction(function () use ($record, $body) {
            $this->remove($record->id);
            DB::insert('insert into objects_fts (object_id, workspace_id, title, body) values (?, ?, ?, ?)', [
                $record->id, $record->workspace_id, $record->title, $body,
            ]);
        });
    }

    public function remove(string $recordId): void
    {
        DB::delete('delete from objects_fts where object_id = ?', [$recordId]);
    }

    public function search(int $workspaceId, string $query, int $limit = 20): Collection
    {
        $match = $this->matchExpression($query);

        if ($match === null) {
            return collect();
        }

        $rows = DB::select(
            "select object_id, snippet(objects_fts, 3, '', '', '…', 12) as snippet
             from objects_fts
             where objects_fts match ? and workspace_id = ?
             order by bm25(objects_fts, 0, 0, 5.0, 1.0)
             limit ?",
            [$match, $workspaceId, $limit],
        );

        /** @var array<int, array{object_id: string, snippet: string}> $rows */
        $rows = array_map(fn ($row) => (array) $row, $rows);

        return collect($rows)->map(fn (array $row) => ['id' => (string) $row['object_id'], 'snippet' => (string) $row['snippet']]);
    }

    public function flush(): void
    {
        DB::delete('delete from objects_fts');
    }

    /**
     * Every word must match, as a prefix. FTS syntax characters are dropped so
     * user input can never break the query.
     */
    private function matchExpression(string $query): ?string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return null;
        }

        return collect($words)->map(fn (string $word) => '"'.$word.'"*')->implode(' ');
    }
}
