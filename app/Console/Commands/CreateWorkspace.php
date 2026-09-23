<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('cidash:create-workspace {name : Team name, e.g. "CI Reitoria"} {--slug= : Defaults to the slugified name}')]
#[Description('Create a workspace (communication team)')]
class CreateWorkspace extends Command
{
    public function handle(): int
    {
        $slug = $this->option('slug') ?: Str::slug($this->argument('name'));

        if (Workspace::where('slug', $slug)->exists()) {
            $this->error("A workspace with slug [{$slug}] already exists.");

            return self::FAILURE;
        }

        Workspace::create(['name' => $this->argument('name'), 'slug' => $slug]);

        $this->info("Workspace created: {$slug}");

        return self::SUCCESS;
    }
}
