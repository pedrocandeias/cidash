<?php

namespace App\Core\Search;

use App\Models\Record;
use Illuminate\Support\Collection;

/**
 * Global full-text search over records (ARCHITECTURE.md §7 "Pesquisa").
 * Implementations must restrict results to the given workspace themselves.
 */
interface Search
{
    public function index(Record $record, string $body): void;

    public function remove(string $recordId): void;

    /**
     * @return Collection<int, array{id: string, snippet: string}> best matches first
     */
    public function search(int $workspaceId, string $query, int $limit = 20): Collection;

    public function flush(): void;
}
