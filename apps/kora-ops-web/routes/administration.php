<?php

use App\Modules\IdentityAccess\Http\Controllers\RoleController;
use App\Modules\IdentityAccess\Http\Controllers\UserController;
use App\Modules\Settings\Http\Controllers\IntegrationConnectionTestController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    $comingSoon = function (string $title, string $description, string $module = 'Bientôt disponible') {
        return fn () => inertia('coming-soon', [
            'title' => $title,
            'description' => $description,
            'module' => $module,
        ]);
    };

    Route::get('administration', fn () => to_route('administration.settings'))
        ->name('administration');

    Route::get('administration/settings', [SettingsController::class, 'index'])
        ->name('administration.settings');
    Route::get('administration/settings/general', [SettingsController::class, 'general'])
        ->name('administration.settings.general');
    Route::patch('administration/settings/general', [SettingsController::class, 'updateGeneral'])
        ->name('administration.settings.general.update');
    Route::get('administration/settings/operations', [SettingsController::class, 'operations'])
        ->name('administration.settings.operations');
    Route::patch('administration/settings/operations', [SettingsController::class, 'updateOperations'])
        ->name('administration.settings.operations.update');
    Route::get('administration/settings/integrations', [SettingsController::class, 'integrations'])
        ->name('administration.settings.integrations');
    Route::patch('administration/settings/integrations', [SettingsController::class, 'updateIntegrations'])
        ->name('administration.settings.integrations.update');
    Route::post('administration/settings/integrations/{provider}/test', IntegrationConnectionTestController::class)
        ->middleware('throttle:settings-probes')
        ->name('administration.settings.integrations.test');

    Route::get('users', [UserController::class, 'index'])->name('users');
    Route::get('users/{user:uuid}', [UserController::class, 'show'])->name('users.show');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::patch('users/{user:uuid}', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/{user:uuid}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::post('users/{user:uuid}/password/reset', [UserController::class, 'resetPassword'])->name('users.password.reset');
    Route::post('users/{user:uuid}/pin/reset', [UserController::class, 'resetPin'])->name('users.pin.reset');
    Route::post('users/{user}/unlock', [UserController::class, 'unlock'])->name('users.unlock');

    Route::get('roles', [RoleController::class, 'index'])->name('roles');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    Route::patch('roles/{role}/metadata', [RoleController::class, 'update'])->name('roles.update');
    Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    Route::post('roles/{role}/restore', [RoleController::class, 'restore'])->name('roles.restore');

    Route::get('terminals', $comingSoon('Terminaux P501', 'Administrez les terminaux autorisés par numéro de série et affectation.', 'Administration'))
        ->name('terminals');
    Route::get('integrations', $comingSoon('Intégrations', 'Supervisez CassKai, MECeF, Mobile Money et les files de synchronisation.', 'Administration'))
        ->name('integrations');
    Route::get('audit', $comingSoon('Journal d’audit', 'Retrouvez chaque action sensible et son contexte.', 'Administration'))
        ->name('audit');
});
