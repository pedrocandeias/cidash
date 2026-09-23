<?php

namespace App\Console\Commands;

use App\Briefing\DailyBriefing;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:generate-briefings')]
#[Description("Generate today's daily briefing of every workspace")]
class GenerateBriefings extends Command
{
    public function handle(DailyBriefing $briefing): int
    {
        foreach (Workspace::all() as $workspace) {
            $briefing->generate($workspace);
            $this->line("{$workspace->name}: briefing generated");
        }

        return self::SUCCESS;
    }
}
