<?php

use App\Modules\Booking\Http\Controllers\BusSeatHoldController;
use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Booking\Http\Controllers\ReservationPassengerController;
use App\Modules\BusNetwork\Http\Controllers\BusCatalogController;
use App\Modules\Departures\Http\Controllers\BusDepartureController;
use App\Modules\DynamicPricing\Http\Controllers\BusQuoteController;
use App\Modules\IdentityAccess\Http\Controllers\AuthDeviceController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOAuthController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOtpController;
use App\Modules\IdentityAccess\Http\Controllers\AuthSessionController;
use App\Modules\IdentityAccess\Http\Controllers\UserPreferenceController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Support\Http\Controllers\HealthController;
use App\Modules\Ticketing\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('health', [HealthController::class, 'index'])->name('health');
    Route::get('health/ping', [HealthController::class, 'ping'])->name('health.ping');

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::middleware('throttle:auth-otp')->group(function (): void {
            Route::post('otp/request', [AuthOtpController::class, 'request'])->name('otp.request');
            Route::post('otp/verify', [AuthOtpController::class, 'verify'])->name('otp.verify');
            Route::post('pin/reset', [AuthSessionController::class, 'resetPin'])->name('pin.reset');
            Route::post('unlock', [AuthSessionController::class, 'unlockAccount'])->name('unlock');
        });

        Route::post('register/complete', [AuthSessionController::class, 'completeRegistration'])
            ->middleware('throttle:auth-login')
            ->name('register.complete');

        Route::post('login', [AuthSessionController::class, 'login'])
            ->middleware('throttle:auth-login')
            ->name('login');

        Route::post('oauth/token', [AuthOAuthController::class, 'token'])
            ->middleware('throttle:auth-login')
            ->name('oauth.token');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthSessionController::class, 'me'])->name('me');
            Route::put('me', [AuthSessionController::class, 'updateProfile'])->name('me.update');
            Route::post('pin/change', [AuthSessionController::class, 'changePin'])->name('pin.change');
            Route::get('preferences', [UserPreferenceController::class, 'index'])->name('preferences.index');
            Route::put('preferences', [UserPreferenceController::class, 'update'])->name('preferences.update');
            Route::get('devices', [AuthDeviceController::class, 'index'])->name('devices.index');
            Route::delete('devices/{device:uuid}', [AuthDeviceController::class, 'destroy'])->name('devices.destroy');
            Route::post('logout', [AuthSessionController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('bus')
        ->name('bus.')
        ->middleware('throttle:bus-search')
        ->group(function (): void {
            Route::get('cities', [BusCatalogController::class, 'cities'])->name('cities.index');
            Route::get('lines', [BusCatalogController::class, 'lines'])->name('lines.index');
            Route::get('lines/{line:uuid}', [BusCatalogController::class, 'line'])->name('lines.show');
            Route::get('departures', [BusDepartureController::class, 'index'])->name('departures.index');
            Route::get('departures/{departure:uuid}', [BusDepartureController::class, 'show'])->name('departures.show');
            Route::get('departures/{departure:uuid}/seats', [BusDepartureController::class, 'seats'])->name('departures.seats.index');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('quotes', [BusQuoteController::class, 'store'])->name('quotes.store');
                Route::post('holds', [BusSeatHoldController::class, 'store'])->name('holds.store');
            });
        });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('payments/methods', [PaymentController::class, 'methods'])
            ->name('payments.methods');
        Route::get('bookings/{reservation:uuid}', [ReservationController::class, 'show'])
            ->name('bookings.show');
        Route::get('bookings/{reservation:uuid}/tickets', [TicketController::class, 'index'])
            ->name('bookings.tickets.index');

        Route::post('bookings/{reservation:uuid}/passengers', [ReservationPassengerController::class, 'store'])
            ->name('bookings.passengers.store');

        Route::delete('holds/{reservation:uuid}', [ReservationController::class, 'destroy'])
            ->name('holds.destroy');

        Route::post('payments/initiate', [PaymentController::class, 'initiate'])
            ->name('payments.initiate');
        Route::get('payments/{payment:uuid}', [PaymentController::class, 'show'])
            ->name('payments.show');
        Route::post('payments/{payment:uuid}/verify', [PaymentController::class, 'verify'])
            ->middleware('throttle:payment-verify')
            ->name('payments.verify');
        Route::post('payments/{payment:uuid}/cancel', [PaymentController::class, 'cancel'])
            ->name('payments.cancel');

        Route::get('tickets/{ticket:uuid}', [TicketController::class, 'show'])
            ->name('tickets.show');
    });

    Route::post('payments/{payment:uuid}/confirm', [PaymentController::class, 'confirm'])
        ->name('payments.confirm');

    // Provider-specific webhook endpoints.
    Route::post('payments/webhook/fedapay', [PaymentController::class, 'webhookFedaPay'])
        ->name('payments.webhook.fedapay');
    Route::post('payments/webhook/mtn-momo', [PaymentController::class, 'webhookMtnMomo'])
        ->name('payments.webhook.mtn-momo');
});
