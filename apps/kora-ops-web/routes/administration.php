<?php

use App\Modules\IdentityAccess\Http\Controllers\RoleController;
use App\Modules\IdentityAccess\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    $comingSoon = function (string $title, string $description, string $module = 'Bientôt disponible') {
        return fn () => inertia('coming-soon', [
            'title' => $title,
            'description' => $description,
            'module' => $module,
        ]);
    };

    Route::get('administration', $comingSoon('Administration', 'Gérez les comptes, rôles, paramètres et intégrations.', 'Administration'))
        ->name('administration');

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
