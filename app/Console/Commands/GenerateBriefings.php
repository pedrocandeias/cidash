<?php

namespace App\Console\Commands;

use App\Briefing\DailyBriefing;
use App\Briefing\WeeklyBriefing;
use App\Mail\BriefingMail;
use App\Models\Briefing;
use App\Models\Workspace;
use App\Support\EmailPreferences;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('cidash:generate-briefings {--weekly : Also generate the weekly briefing (done on Mondays anyway)}')]
#[Description("Generate today's daily briefing of every workspace, and the weekly one on Mondays")]
class GenerateBriefings extends Command
{
    public function handle(DailyBriefing $daily, WeeklyBriefing $weekly, EmailPreferences $preferences): int
    {
        $withWeekly = $this->option('weekly') || now()->isMonday();

        foreach (Workspace::all() as $workspace) {
            $this->send($daily->generate($workspace), $workspace, 'daily_briefing', $preferences);
            if ($withWeekly) {
                $this->send($weekly->generate($workspace), $workspace, 'weekly_briefing', $preferences);
            }
            $this->line("{$workspace->name}: briefing generated".($withWeekly ? ' (with weekly)' : ''));
        }

        return self::SUCCESS;
    }

    private function send(Briefing $briefing, Workspace $workspace, string $kind, EmailPreferences $preferences): void
    {
        foreach ($workspace->members()->get() as $user) {
            if (! $preferences->wants($user, $kind)) {
                continue;
            }

            // One failed delivery must not stop the others.
            try {
                Mail::to($user)->send(new BriefingMail($briefing, $workspace->name));
            } catch (Throwable $e) {
                report($e);
                $this->warn("{$user->email}: {$e->getMessage()}");
            }
        }
    }
}
