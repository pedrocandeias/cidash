<?php

namespace App\Console\Commands;

use App\Core\Scopes\WorkspaceScope;
use App\Models\MonitoringRule;
use App\Models\Source;
use App\Monitoring\Ingestor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:fetch-sources {--all : Fetch every active source and rule, even if not due}')]
#[Description('Collect news from the due sources and run the Google News searches of the monitoring rules')]
class FetchSources extends Command
{
    public function handle(Ingestor $ingestor): int
    {
        $sources = Source::where('active', true)->get()
            ->reject(fn (Source $source) => $source->isSystem())
            ->filter(fn (Source $source) => $this->option('all') || $source->isDue());

        foreach ($sources as $source) {
            $new = $ingestor->run($source);
            $this->line("{$source->name}: {$new} new".($source->last_error ? " (error: {$source->last_error})" : ''));
        }

        // Rules span every workspace, so the workspace scope is removed explicitly.
        $rules = MonitoringRule::withoutGlobalScope(WorkspaceScope::class)
            ->where('active', true)
            ->where('google_news', true)
            ->whereHas('workspace', fn ($query) => $query->whereNull('archived_at'))
            ->get()
            ->filter(fn (MonitoringRule $rule) => $this->option('all') || $rule->last_fetched_at === null || $rule->last_fetched_at->addMinutes(30)->lte(now()));

        if ($rules->isNotEmpty()) {
            $system = Source::system();
            foreach ($rules as $rule) {
                $this->line("Rule {$rule->name}: {$ingestor->runRule($rule, $system)} new mentions");
            }
        }

        return self::SUCCESS;
    }
}
