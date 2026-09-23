<?php

namespace App\Console\Commands;

use App\Core\Scopes\WorkspaceScope;
use App\Models\Reminder;
use App\Notifications\ReminderDue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cidash:send-reminders')]
#[Description('Deliver due reminders as in-app notifications')]
class SendReminders extends Command
{
    public function handle(): int
    {
        // Runs for every workspace, so the workspace scope is removed explicitly.
        $due = Reminder::withoutGlobalScope(WorkspaceScope::class)
            ->with(['user', 'record' => fn ($query) => $query->withoutGlobalScope(WorkspaceScope::class)])
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->limit(500)
            ->get();

        foreach ($due as $reminder) {
            if ($reminder->record !== null) {
                $reminder->user->notify(new ReminderDue($reminder->record));
            }

            $reminder->forceFill(['sent_at' => now()])->save();
        }

        $this->info("Sent {$due->count()} reminders.");

        return self::SUCCESS;
    }
}
