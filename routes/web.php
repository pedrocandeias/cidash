<?php

use App\Http\Controllers\Auth\InvitationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'workspace'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('invitation/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation', [InvitationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('invitation.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
