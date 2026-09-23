<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('cidash:backup {--keep=14 : Number of backups to keep}')]
#[Description('Create a consistent copy of the SQLite database with VACUUM INTO and prune old copies')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $path = $dir.'/cidash-'.now()->format('Ymd-His').'.sqlite';

        // Safe while the app is writing, unlike copying the file.
        DB::statement('VACUUM INTO ?', [$path]);

        $backups = collect(File::glob($dir.'/cidash-*.sqlite'))->sort()->values();
        $backups->slice(0, max(0, $backups->count() - (int) $this->option('keep')))
            ->each(fn (string $old) => File::delete($old));

        $this->info("Backup created: {$path}");

        return self::SUCCESS;
    }
}
