<?php

use App\Modules\AppDistribution\Http\Controllers\AppReleaseController;
use App\Modules\IdentityAccess\Http\Controllers\RoleController;
use App\Modules\IdentityAccess\Http\Controllers\TerminalManagementController;
use App\Modules\IdentityAccess\Http\Controllers\UserController;
use App\Modules\Monitoring\Http\Controllers\MonitoringController;
use App\Modules\Settings\Http\Controllers\IntegrationConnectionTestController;
use App\Modules\Settings\Http\Controllers\IntegrationTestSmsController;
use App\Modules\Settings\Http\Controllers\SettingsController;
use App\Modules\Wallet\Http\Controllers\OpsWalletRestitutionController;
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
    Route::get('administration/settings/wallet', [SettingsController::class, 'wallet'])
        ->name('administration.settings.wallet');
    Route::patch('administration/settings/wallet', [SettingsController::class, 'updateWallet'])
        ->name('administration.settings.wallet.update');
    Route::get('administration/settings/integrations', [SettingsController::class, 'integrations'])
        ->name('administration.settings.integrations');
    Route::patch('administration/settings/integrations', [SettingsController::class, 'updateIntegrations'])
        ->name('administration.settings.integrations.update');
    Route::post('administration/settings/integrations/{provider}/test', IntegrationConnectionTestController::class)
        ->middleware('throttle:settings-probes')
        ->name('administration.settings.integrations.test');
    Route::post('administration/settings/integrations/test-sms', IntegrationTestSmsController::class)
        ->middleware('throttle:settings-probes')
        ->name('administration.settings.integrations.test-sms');

    Route::get('administration/wallet-restitutions', [OpsWalletRestitutionController::class, 'page'])
        ->name('administration.wallet-restitutions.index');
    Route::post('administration/wallet-restitutions', [OpsWalletRestitutionController::class, 'store'])
        ->name('administration.wallet-restitutions.store');
    Route::post('administration/wallet-restitutions/{restitution:uuid}/approve', [OpsWalletRestitutionController::class, 'approve'])
        ->name('administration.wallet-restitutions.approve');
    Route::post('administration/wallet-restitutions/{restitution:uuid}/reject', [OpsWalletRestitutionController::class, 'reject'])
        ->name('administration.wallet-restitutions.reject');
    Route::post('administration/wallet-restitutions/{restitution:uuid}/mark-paid', [OpsWalletRestitutionController::class, 'markPaid'])
        ->name('administration.wallet-restitutions.mark-paid');

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

    Route::get('terminals', [TerminalManagementController::class, 'index'])
        ->name('terminals');
    Route::get('administration/monitoring', MonitoringController::class)
        ->name('administration.monitoring');
    Route::get('terminals/{terminal:uuid}', [TerminalManagementController::class, 'show'])
        ->name('terminals.show');

    Route::get('administration/releases', [AppReleaseController::class, 'index'])
        ->name('administration.releases');
    Route::post('administration/releases', [AppReleaseController::class, 'store'])
        ->name('administration.releases.store');
    Route::patch('administration/releases/{release:uuid}', [AppReleaseController::class, 'update'])
        ->name('administration.releases.update');
    Route::post('administration/releases/{release:uuid}/archive', [AppReleaseController::class, 'archive'])
        ->name('administration.releases.archive');
    Route::post('administration/releases/{release:uuid}/restore', [AppReleaseController::class, 'restore'])
        ->name('administration.releases.restore');
    Route::post('administration/releases/store', [AppReleaseController::class, 'storeStore'])
        ->name('administration.releases.store-store');
    Route::post('administration/releases/{release:uuid}/confirm-store', [AppReleaseController::class, 'confirmStore'])
        ->name('administration.releases.confirm-store');
    Route::post('administration/releases/{release:uuid}/push-campaign', [AppReleaseController::class, 'dispatchCampaign'])
        ->name('administration.releases.push-campaign');
    Route::post('administration/releases/push-campaigns/{campaign:uuid}/retry', [AppReleaseController::class, 'retryCampaign'])
        ->name('administration.releases.push-campaign-retry');
    Route::get('integrations', $comingSoon('Intégrations', 'Supervisez CassKai, MECeF, Mobile Money et les files de synchronisation.', 'Administration'))
        ->name('integrations');
    Route::get('audit', $comingSoon('Journal d’audit', 'Retrouvez chaque action sensible et son contexte.', 'Administration'))
        ->name('audit');
});
