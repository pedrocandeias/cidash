<?php

use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\SourceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:super-admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('email', [MailSettingsController::class, 'edit'])->name('email.edit');
    Route::put('email', [MailSettingsController::class, 'update'])->name('email.update');
    Route::post('email/test', [MailSettingsController::class, 'test'])
        ->middleware('throttle:6,1')
        ->name('email.test');

    Route::get('sources', [SourceController::class, 'index'])->name('sources.index');
    Route::post('sources', [SourceController::class, 'store'])->name('sources.store');
    Route::patch('sources/{source}', [SourceController::class, 'update'])->name('sources.update');
    Route::delete('sources/{source}', [SourceController::class, 'destroy'])->name('sources.destroy');
    Route::post('sources/{source}/fetch', [SourceController::class, 'fetch'])->name('sources.fetch');

    Route::get('workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::patch('workspaces/{workspace}', [WorkspaceController::class, 'update'])->name('workspaces.update');
    Route::post('workspaces/{workspace}/managers', [WorkspaceController::class, 'addManager'])->name('workspaces.managers.store');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('users/{user}/two-factor-reset', [UserController::class, 'resetTwoFactor'])->name('users.two-factor-reset');
    Route::post('users/{user}/memberships', [UserController::class, 'addMembership'])->name('users.memberships.store');
    Route::patch('users/{user}/memberships/{workspace}', [UserController::class, 'updateMembership'])->name('users.memberships.update');
    Route::delete('users/{user}/memberships/{workspace}', [UserController::class, 'removeMembership'])->name('users.memberships.destroy');
});
