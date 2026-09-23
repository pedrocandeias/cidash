<?php

use App\Http\Controllers\Admin\MailSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('email', [MailSettingsController::class, 'edit'])->name('email.edit');
    Route::put('email', [MailSettingsController::class, 'update'])->name('email.update');
    Route::post('email/test', [MailSettingsController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('email.test');
});
