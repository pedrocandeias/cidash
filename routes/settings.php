<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TeamController;
use App\Http\Controllers\Settings\TermsController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware('auth')->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');
});

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::get('settings/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('settings/team', [TeamController::class, 'store'])->name('team.store');
    Route::patch('settings/team/{member}', [TeamController::class, 'update'])->name('team.update');
    Route::delete('settings/team/{member}', [TeamController::class, 'destroy'])->name('team.destroy');
    Route::post('settings/team/{member}/invitation', [TeamController::class, 'resendInvitation'])
        ->middleware('throttle:6,1')
        ->name('team.invitation');

    Route::get('settings/terms', [TermsController::class, 'index'])->name('terms.index');
    Route::patch('settings/terms/{kind}/{id}', [TermsController::class, 'update'])->name('terms.update');
    Route::post('settings/terms/{kind}/{id}/merge', [TermsController::class, 'merge'])->name('terms.merge');
    Route::delete('settings/terms/{kind}/{id}', [TermsController::class, 'destroy'])->name('terms.destroy');
});
