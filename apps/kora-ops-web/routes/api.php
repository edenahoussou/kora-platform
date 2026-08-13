<?php

use App\Modules\IdentityAccess\Http\Controllers\AuthDeviceController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOtpController;
use App\Modules\IdentityAccess\Http\Controllers\AuthSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'kora-platform',
            'scope' => 'mvp-bus',
        ]);
    })->name('health');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:auth-otp')->group(function (): void {
            Route::post('otp/request', [AuthOtpController::class, 'request'])->name('otp.request');
            Route::post('otp/verify', [AuthOtpController::class, 'verify'])->name('otp.verify');
            Route::post('pin/reset', [AuthSessionController::class, 'resetPin'])->name('pin.reset');
        });

        Route::post('register/complete', [AuthSessionController::class, 'completeRegistration'])
            ->middleware('throttle:auth-login')
            ->name('register.complete');

        Route::post('login', [AuthSessionController::class, 'login'])
            ->middleware('throttle:auth-login')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthSessionController::class, 'me'])->name('me');
            Route::get('devices', [AuthDeviceController::class, 'index'])->name('devices.index');
            Route::delete('devices/{device:uuid}', [AuthDeviceController::class, 'destroy'])->name('devices.destroy');
            Route::post('logout', [AuthSessionController::class, 'logout'])->name('logout');
        });
    });
});
