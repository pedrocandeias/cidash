<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Production runs on plain LAMP with a single cron entry (`schedule:run` every minute),
// so the queue is drained from the scheduler instead of a supervised worker.
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('cidash:send-reminders')->everyMinute()->withoutOverlapping();

Schedule::command('cidash:fetch-sources')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('cidash:evaluate-alerts')->everyFiveMinutes()->withoutOverlapping();

// Alerts were evaluated at 06:55, so the briefing lists the current ones.
Schedule::command('cidash:generate-briefings')->weekdays()->at('07:00');

Schedule::command('cidash:backup')->dailyAt('03:00');
