<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $reitoria = Workspace::create(['name' => 'CI Reitoria', 'slug' => 'reitoria']);

        User::factory()->superAdmin()->inWorkspace($reitoria, WorkspaceRole::Manager)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
