<?php

use App\Modules\AssistedBooking\Http\Controllers\AssistedBookingAgencyController;
use App\Modules\AssistedBooking\Http\Controllers\AssistedBookingApiController;
use App\Modules\Boarding\Http\Controllers\OpsBoardingController;
use App\Modules\Boarding\Http\Controllers\OpsSeatReleaseController;
use App\Modules\Booking\Http\Controllers\ItineraryController;
use App\Modules\Booking\Http\Controllers\ReservationController;
use App\Modules\Booking\Http\Controllers\ReservationPassengerController;
use App\Modules\BusNetwork\Http\Controllers\BusCatalogController;
use App\Modules\CompanyAccounts\Http\Controllers\CompanyAccountController;
use App\Modules\Departures\Http\Controllers\BusDepartureController;
use App\Modules\Departures\Http\Controllers\OpsDepartureController;
use App\Modules\DynamicPricing\Http\Controllers\GroupDiscountRuleController;
use App\Modules\IdentityAccess\Http\Controllers\AuthDeviceController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOAuthController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOtpController;
use App\Modules\IdentityAccess\Http\Controllers\AuthSessionController;
use App\Modules\IdentityAccess\Http\Controllers\TerminalOpsMobileController;
use App\Modules\IdentityAccess\Http\Controllers\UserPreferenceController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Support\Http\Controllers\HealthController;
use App\Modules\Sync\Http\Controllers\OpsSyncController;
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

    // OPS Mobile — handshake et opérations du terminal P501. L'auth de
    // l'utilisateur (login, PIN, OTP) reste celle de auth/login ci-dessus ;
    // ces routes ajoutent le contrôle du terminal par-dessus la session.
    Route::prefix('ops-mobile/terminal')
        ->name('ops-mobile.terminal.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::post('handshake', [TerminalOpsMobileController::class, 'handshake'])
                ->middleware('throttle:terminal-handshake')
                ->name('handshake');

            Route::middleware('terminal.active')->group(function (): void {
                Route::post('sync', [TerminalOpsMobileController::class, 'sync'])->name('sync');
                Route::post('activity', [TerminalOpsMobileController::class, 'activity'])->name('activity');
            });
        });

    // OPS Mobile — planning et manifeste du convoyeur (MEZ-84 suite,
    // remplace les écrans mockés de MEZ-11). Même garde que ci-dessus :
    // session existante (auth:sanctum) + terminal enregistré et actif.
    Route::prefix('ops/departures')
        ->name('ops.departures.')
        ->middleware(['auth:sanctum', 'terminal.active'])
        ->group(function (): void {
            Route::get('/', [OpsDepartureController::class, 'index'])->name('index');
            Route::get('{departure:uuid}', [OpsDepartureController::class, 'show'])->name('show');
            Route::get('{departure:uuid}/manifest', [OpsDepartureController::class, 'manifest'])->name('manifest');
        });

    // OPS Mobile — validation d'embarquement (MEZ-84 suite). Le scan lui
    // même exige un terminal actif ; l'annulation exceptionnelle est une
    // action de correction ops (permission dédiée) qui n'en a pas besoin.
    Route::prefix('ops/boarding')
        ->name('ops.boarding.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::post('/', [OpsBoardingController::class, 'store'])
                ->middleware('terminal.active')
                ->name('store');
            Route::post('{boardingEvent:uuid}/reverse', [OpsBoardingController::class, 'reverse'])->name('reverse');
        });

    // OPS Mobile — descente anticipée et clôture no-show (MEZ-84 suite).
    // Les deux exigent un terminal actif : ce sont des actions faites
    // depuis le P501, contrairement à l'annulation d'embarquement.
    Route::prefix('ops/seat-releases')
        ->name('ops.seat-releases.')
        ->middleware(['auth:sanctum', 'terminal.active'])
        ->group(function (): void {
            Route::post('/', [OpsSeatReleaseController::class, 'store'])->name('store');
            Route::post('no-show-closure', [OpsSeatReleaseController::class, 'closeNoShows'])->name('no-show-closure');
        });

    // OPS Mobile — synchronisation hors ligne (MEZ-84 suite). Enveloppe
    // sync_batches/sync_operations autour des actions de traitement déjà
    // construites (embarquement, no-show, libération) — même garde que
    // les autres actions terrain.
    Route::prefix('ops/sync')
        ->name('ops.sync.')
        ->middleware(['auth:sanctum', 'terminal.active'])
        ->group(function (): void {
            Route::post('manifest-versions', [OpsSyncController::class, 'manifestVersions'])->name('manifest-versions');
            Route::post('batches', [OpsSyncController::class, 'submitBatch'])->name('batches');
        });

    Route::prefix('bus')
        ->name('bus.')
        ->middleware('throttle:bus-search')
        ->group(function (): void {
            Route::get('cities', [BusCatalogController::class, 'cities'])->name('cities.index');
            Route::get('lines', [BusCatalogController::class, 'lines'])->name('lines.index');
            Route::get('lines/{line:uuid}', [BusCatalogController::class, 'line'])->name('lines.show');
            Route::get('departures/{departure:uuid}/seats', [BusDepartureController::class, 'seats'])->name('departures.seats.index');
            Route::get('itineraries', [ItineraryController::class, 'index'])
                ->middleware('throttle:bus-itinerary-search')
                ->name('itineraries.index');

            Route::middleware('auth:sanctum')->group(function (): void {
                Route::post('itinerary-quotes', [ItineraryController::class, 'quote'])
                    ->middleware('throttle:bus-itinerary-actions')
                    ->name('itinerary-quotes.store');
                Route::post('itinerary-holds', [ItineraryController::class, 'hold'])
                    ->middleware('throttle:bus-itinerary-actions')
                    ->name('itinerary-holds.store');
            });
        });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('assisted-bookings/agencies', [AssistedBookingAgencyController::class, 'index'])
            ->middleware('throttle:bus-itinerary-actions')
            ->name('assisted-bookings.agencies.index');
        Route::post('bookings/{reservation:uuid}/assistance', [AssistedBookingApiController::class, 'store'])
            ->middleware('throttle:bus-itinerary-actions')
            ->name('bookings.assistance.store');
        Route::get('bookings/{reservation:uuid}/assistance', [AssistedBookingApiController::class, 'show'])
            ->middleware('throttle:bus-itinerary-actions')
            ->name('bookings.assistance.show');
        Route::post('bookings/{reservation:uuid}/assistance/resume-online', [AssistedBookingApiController::class, 'resumeOnline'])
            ->middleware('throttle:bus-itinerary-actions')
            ->name('bookings.assistance.resume-online');

        Route::get('company-accounts', [CompanyAccountController::class, 'index'])->name('company-accounts.index');
        Route::post('company-accounts', [CompanyAccountController::class, 'store'])->name('company-accounts.store');
        Route::put('company-accounts/{companyAccount:uuid}', [CompanyAccountController::class, 'update'])->name('company-accounts.update');
        Route::post('company-accounts/{companyAccount:uuid}/deactivate', [CompanyAccountController::class, 'deactivate'])->name('company-accounts.deactivate');
        Route::post('company-accounts/{companyAccount:uuid}/members', [CompanyAccountController::class, 'addMember'])->name('company-accounts.members.store');
        Route::get('group-discounts', [GroupDiscountRuleController::class, 'index'])->name('group-discounts.index');
        Route::post('group-discounts', [GroupDiscountRuleController::class, 'store'])->name('group-discounts.store');
        Route::put('group-discounts/{groupDiscountRule:uuid}', [GroupDiscountRuleController::class, 'update'])->name('group-discounts.update');
        Route::get('group-booking-settings', [GroupDiscountRuleController::class, 'settings'])->name('group-booking-settings.show');
        Route::put('group-booking-settings', [GroupDiscountRuleController::class, 'updateSettings'])->name('group-booking-settings.update');
        Route::get('payments/methods', [PaymentController::class, 'methods'])
            ->name('payments.methods');

        Route::get('bookings/{reservation:uuid}', [ReservationController::class, 'show'])
            ->name('bookings.show');
        Route::delete('bookings/{reservation:uuid}', [ReservationController::class, 'destroy'])
            ->name('bookings.destroy');
        Route::put('bookings/{reservation:uuid}/promo-code', [ReservationController::class, 'updatePromoCode'])
            ->middleware('throttle:bus-itinerary-actions')
            ->name('bookings.promo-code.update');
        Route::post('bookings/{reservation:uuid}/passengers', [ReservationPassengerController::class, 'store'])
            ->name('bookings.passengers.store');
        Route::post('bookings/{reservation:uuid}/cancel', [ReservationController::class, 'cancel'])
            ->name('bookings.cancel');
        Route::post('bookings/{reservation:uuid}/passengers/{passenger:uuid}/cancel', [ReservationController::class, 'cancelPassenger'])
            ->name('bookings.passengers.cancel');
        Route::get('bookings/{reservation:uuid}/tickets', [TicketController::class, 'index'])
            ->name('bookings.tickets.index');

        Route::post('payments/initiate', [PaymentController::class, 'initiate'])
            ->name('payments.initiate');
        Route::get('payments/{payment:uuid}', [PaymentController::class, 'show'])
            ->name('payments.show');
        Route::post('payments/{payment:uuid}/verify', [PaymentController::class, 'verify'])
            ->middleware('throttle:payment-verify')
            ->name('payments.verify');
        Route::post('payments/{payment:uuid}/cancel', [PaymentController::class, 'cancel'])
            ->name('payments.cancel');

        Route::get('tickets', [TicketController::class, 'mine'])
            ->name('tickets.index');
        Route::get('tickets/{ticket:uuid}', [TicketController::class, 'show'])
            ->name('tickets.show');
        Route::get('tickets/{ticket:uuid}/pdf', [TicketController::class, 'downloadPdf'])
            ->name('tickets.pdf');
    });

    Route::post('payments/{payment:uuid}/confirm', [PaymentController::class, 'confirm'])
        ->name('payments.confirm');

    // Provider-specific webhook endpoints.
    Route::post('payments/webhook/fedapay', [PaymentController::class, 'webhookFedaPay'])
        ->name('payments.webhook.fedapay');
    Route::post('payments/webhook/mtn-momo', [PaymentController::class, 'webhookMtnMomo'])
        ->name('payments.webhook.mtn-momo');

    // Provider callback endpoint (redirect or POST after payment processing).
    Route::match(['get', 'post'], 'payments/callback/fedapay', [PaymentController::class, 'callbackFedaPay'])
        ->name('payments.callback.fedapay');
});
