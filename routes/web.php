<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\BriefingController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentItemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LinkController;
use App\Http\Controllers\MentionController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PressRequestController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('briefings', [BriefingController::class, 'index'])->name('briefings.index');
    Route::get('briefings/today', [BriefingController::class, 'today'])->name('briefings.today');
    Route::get('briefings/week', [BriefingController::class, 'week'])->name('briefings.week');
    Route::get('briefings/{briefing}', [BriefingController::class, 'show'])->name('briefings.show');
    Route::post('briefings/{briefing}/refresh', [BriefingController::class, 'refresh'])->name('briefings.refresh');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{alert}', [AlertController::class, 'update'])->name('alerts.update');

    Route::resource('tasks', TaskController::class)->except(['create', 'edit']);

    Route::get('events/feed', [CalendarEventController::class, 'feed'])->name('events.feed');
    Route::resource('events', CalendarEventController::class)->except(['create', 'edit']);

    Route::resource('notices', NoticeController::class)->except(['create', 'edit']);

    Route::resource('press', PressRequestController::class)->except(['create', 'edit']);

    Route::resource('content', ContentItemController::class)->except(['create', 'edit']);

    Route::resource('campaigns', CampaignController::class)->except(['create', 'edit']);

    Route::get('people/areas/suggest', [PersonController::class, 'suggestAreas'])->name('people.areas.suggest');
    Route::get('people/{person}/photo', [PersonController::class, 'photo'])->name('people.photo');
    Route::resource('people', PersonController::class)->except(['create', 'edit']);

    Route::resource('news', NewsController::class)->only(['index', 'show', 'update']);
    Route::resource('mentions', MentionController::class)->only(['index', 'show', 'update']);

    Route::get('records/search', SearchController::class)->name('records.search');
    Route::post('records/{record}/links', [LinkController::class, 'store'])->name('links.store');
    Route::delete('links/{link}', [LinkController::class, 'destroy'])->name('links.destroy');

    Route::post('records/{record}/reminders', [ReminderController::class, 'store'])->name('reminders.store');
    Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');

    Route::get('tags/suggest', [TagController::class, 'suggest'])->name('tags.suggest');

    Route::post('records/{record}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});

Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
