<?php

namespace App\Console\Commands;

use App\Core\RecordTypes;
use App\Core\Scopes\WorkspaceScope;
use App\Core\Search\Search;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

#[Signature('cidash:search-reindex')]
#[Description('Rebuild the global search index from every record')]
class ReindexSearch extends Command
{
    public function handle(Search $search): int
    {
        $search->flush();
        $count = 0;

        foreach (RecordTypes::models() as $model) {
            // Every workspace is indexed, so the scope is removed explicitly.
            $model::withoutGlobalScope(WorkspaceScope::class)
                ->with(['record' => fn ($query) => $query->withoutGlobalScope(WorkspaceScope::class)])
                ->chunk(200, function ($items) use (&$count) {
                    $items->each(function (Model $item) use (&$count) {
                        if (method_exists($item, 'indexForSearch')) {
                            $item->indexForSearch();
                            $count++;
                        }
                    });
                });
        }

        $this->info("Indexed {$count} records.");

        return self::SUCCESS;
    }
}
