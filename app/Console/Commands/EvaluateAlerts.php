<?php

namespace App\Console\Commands;

use App\Alerts\Evaluator;
use App\Models\Workspace;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:evaluate-alerts')]
#[Description('Run the alert rules of every workspace, opening and resolving alerts')]
class EvaluateAlerts extends Command
{
    public function handle(Evaluator $evaluator): int
    {
        foreach (Workspace::all() as $workspace) {
            $this->line("{$workspace->name}: {$evaluator->run($workspace)} new alerts");
        }

        return self::SUCCESS;
    }
}
