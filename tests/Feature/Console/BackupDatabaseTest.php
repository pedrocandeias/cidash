<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/backups');
        File::deleteDirectory($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_it_creates_a_readable_sqlite_backup(): void
    {
        $this->artisan('cidash:backup')->assertSuccessful();

        $backups = File::glob($this->dir.'/cidash-*.sqlite');
        $this->assertCount(1, $backups);

        $pdo = new \PDO('sqlite:'.$backups[0]);
        $this->assertSame('ok', $pdo->query('pragma integrity_check')->fetchColumn());
    }

    public function test_it_keeps_only_the_most_recent_backups(): void
    {
        File::ensureDirectoryExists($this->dir);
        foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $stamp) {
            File::put($this->dir."/cidash-{$stamp}.sqlite", '');
        }

        $this->artisan('cidash:backup', ['--keep' => 2])->assertSuccessful();

        $remaining = collect(File::glob($this->dir.'/cidash-*.sqlite'))->map(fn ($f) => basename($f));
        $this->assertCount(2, $remaining);
        $this->assertNotContains('cidash-20260102-000000.sqlite', $remaining);
    }
}
