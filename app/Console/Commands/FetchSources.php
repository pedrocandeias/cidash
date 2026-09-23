<?php

namespace App\Console\Commands;

use App\Models\Source;
use App\Monitoring\Ingestor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:fetch-sources {--all : Fetch every active source, even if not due}')]
#[Description('Collect news from the active sources that are due')]
class FetchSources extends Command
{
    public function handle(Ingestor $ingestor): int
    {
        $sources = Source::where('active', true)->get()
            ->filter(fn (Source $source) => $this->option('all') || $source->isDue());

        foreach ($sources as $source) {
            $new = $ingestor->run($source);
            $this->line("{$source->name}: {$new} new".($source->last_error ? " (error: {$source->last_error})" : ''));
        }

        return self::SUCCESS;
    }
}
