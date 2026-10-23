<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\ProfilePreferenceController;
use App\Http\Controllers\Settings\ProfileSessionController;
use App\Http\Controllers\Settings\SecurityController;
use App\Modules\Notifications\Http\Controllers\OpsWebPushPreferenceController;
use Illuminate\Routing\RedirectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', RedirectController::class)
        ->defaults('destination', '/settings/profile')
        ->defaults('status', 302);

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('settings/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::delete('settings/profile/avatar', [ProfileController::class, 'destroyAvatar'])->name('profile.avatar.destroy');
    Route::put('settings/profile/preferences', [ProfilePreferenceController::class, 'update'])->name('profile.preferences.update');
    Route::delete('settings/profile/sessions', [ProfileSessionController::class, 'destroyOthers'])->name('profile.sessions.destroy-others');
    Route::delete('settings/profile/sessions/{session}', [ProfileSessionController::class, 'destroySession'])->name('profile.sessions.destroy');
    Route::delete('settings/profile/devices/{device}', [ProfileSessionController::class, 'destroyDevice'])->name('profile.devices.destroy');
    Route::delete('settings/profile/terminals/{terminal}', [ProfileSessionController::class, 'destroyTerminal'])->name('profile.terminals.destroy');

    Route::post('settings/notifications/push', [OpsWebPushPreferenceController::class, 'update'])->name('settings.notifications.push.update');

    Route::get('settings/notifications', RedirectController::class)
        ->defaults('destination', '/settings/profile')
        ->defaults('status', 302)
        ->name('settings.notifications');
    Route::get('settings/appearance', RedirectController::class)
        ->defaults('destination', '/settings/profile')
        ->defaults('status', 302)
        ->name('appearance.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', RedirectController::class)
        ->defaults('destination', '/settings/profile?tab=security')
        ->defaults('status', 302)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('profile.edit'),
        'manage' => route('profile.edit'),
    ]);
})->name('well-known.passkeys');
