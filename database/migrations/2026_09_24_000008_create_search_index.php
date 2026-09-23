<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Full-text index of every record (App\Core\Search). SQLite FTS5 only; another
 * database engine needs its own implementation of the Search contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        // remove_diacritics 2: "galaxias" matches "galáxias"; case is ignored.
        DB::statement("CREATE VIRTUAL TABLE objects_fts USING fts5(
            object_id UNINDEXED,
            workspace_id UNINDEXED,
            title,
            body,
            tokenize = 'unicode61 remove_diacritics 2'
        )");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TABLE IF EXISTS objects_fts');
        }
    }
};
