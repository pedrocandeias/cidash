<?php

namespace App\Console\Commands;

use App\Briefing\DailyBriefing;
use App\Briefing\WeeklyBriefing;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:generate-briefings {--weekly : Also generate the weekly briefing (done on Mondays anyway)}')]
#[Description("Generate today's daily briefing of every workspace, and the weekly one on Mondays")]
class GenerateBriefings extends Command
{
    public function handle(DailyBriefing $daily, WeeklyBriefing $weekly): int
    {
        $withWeekly = $this->option('weekly') || now()->isMonday();

        foreach (Workspace::all() as $workspace) {
            $daily->generate($workspace);
            if ($withWeekly) {
                $weekly->generate($workspace);
            }
            $this->line("{$workspace->name}: briefing generated".($withWeekly ? ' (with weekly)' : ''));
        }

        return self::SUCCESS;
    }
}
