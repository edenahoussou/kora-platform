<?php

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
use App\Modules\Departures\Http\Controllers\OpsDepartureProgressController;
use App\Modules\Departures\Http\Controllers\OpsPassengerCorrectionController;
use App\Modules\DynamicPricing\Http\Controllers\GroupDiscountRuleController;
use App\Modules\FleetLite\Http\Controllers\OpsIncidentController;
use App\Modules\IdentityAccess\Http\Controllers\AuthDeviceController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOAuthController;
use App\Modules\IdentityAccess\Http\Controllers\AuthOtpController;
use App\Modules\IdentityAccess\Http\Controllers\AuthPushTokenController;
use App\Modules\IdentityAccess\Http\Controllers\AuthSessionController;
use App\Modules\IdentityAccess\Http\Controllers\OpsSessionController;
use App\Modules\IdentityAccess\Http\Controllers\TerminalOpsMobileController;
use App\Modules\IdentityAccess\Http\Controllers\UserPreferenceController;
use App\Modules\Notifications\Http\Controllers\OpsNotificationController;
use App\Modules\Notifications\Http\Controllers\OpsNotificationPreferenceController;
use App\Modules\Notifications\Http\Controllers\OpsSupportChatConfigurationController;
use App\Modules\Notifications\Http\Controllers\OpsWebPushPreferenceController;
use App\Modules\Notifications\Http\Controllers\PassengerFirebaseConfigurationController;
use App\Modules\Notifications\Http\Controllers\PassengerNotificationController;
use App\Modules\Notifications\Http\Controllers\PassengerSupportChatConfigurationController;
use App\Modules\Payments\Http\Controllers\PaymentController;
use App\Modules\Support\Http\Controllers\HealthController;
use App\Modules\Sync\Http\Controllers\OpsSyncController;
use App\Modules\Ticketing\Http\Controllers\TicketController;
use App\Modules\Tracking\Http\Controllers\FleetTrackingController;
use App\Modules\Tracking\Http\Controllers\OpsTrackingController;
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
            Route::put('devices/{device:uuid}/push-token', [AuthPushTokenController::class, 'update'])->name('devices.push-token.update');
            Route::prefix('passenger/notifications')->name('passenger.notifications.')->group(function (): void {
                Route::get('/', [PassengerNotificationController::class, 'index'])->name('index');
                Route::get('unread-count', [PassengerNotificationController::class, 'unreadCount'])->name('unread-count');
                Route::post('read-all', [PassengerNotificationController::class, 'markAllRead'])->name('read-all');
                Route::post('{notification}/read', [PassengerNotificationController::class, 'markRead'])->name('read');
            });
            Route::get('passenger/firebase-configuration', [PassengerFirebaseConfigurationController::class, 'show'])->name('passenger.firebase-configuration');
            Route::get('passenger/support-chat-configuration', [PassengerSupportChatConfigurationController::class, 'show'])->name('passenger.support-chat-configuration');
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

            Route::middleware('ops.session')->group(function (): void {
                Route::post('sync', [TerminalOpsMobileController::class, 'sync'])->name('sync');
                Route::post('activity', [TerminalOpsMobileController::class, 'activity'])->name('activity');
            });
        });

    // OPS Mobile — session OPS liée au terminal (MEZ-95). L'ouverture est le
    // handshake ci-dessus ; la consultation et le renouvellement exigent une
    // session valide sur CE terminal, la déconnexion non (toujours possible).
    Route::prefix('ops-mobile/session')
        ->name('ops-mobile.session.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::delete('/', [OpsSessionController::class, 'destroy'])->name('destroy');

            Route::middleware('ops.session')->group(function (): void {
                Route::get('/', [OpsSessionController::class, 'show'])->name('show');
                Route::post('renew', [OpsSessionController::class, 'renew'])
                    ->middleware('throttle:terminal-handshake')
                    ->name('renew');
            });
        });

    // OPS Mobile — planning et manifeste du convoyeur (MEZ-84 suite,
    // remplace les écrans mockés de MEZ-11). Même garde que ci-dessus :
    // session existante (auth:sanctum) + terminal enregistré et actif.
    Route::prefix('ops/departures')
        ->name('ops.departures.')
        ->middleware(['auth:sanctum', 'ops.session'])
        ->group(function (): void {
            Route::get('/', [OpsDepartureController::class, 'index'])->name('index');
            Route::get('{departure:uuid}', [OpsDepartureController::class, 'show'])->name('show');
            Route::get('{departure:uuid}/manifest', [OpsDepartureController::class, 'manifest'])->name('manifest');
            // Manifeste frontière PDF (A4) — mode téléphone, MEZ-94.
            Route::get('{departure:uuid}/manifest/pdf', [OpsDepartureController::class, 'manifestPdf'])->name('manifest-pdf');

            // Correction des informations d'un passager par le convoyeur
            // (saisie erronée constatée à l'embarquement). Même contrainte
            // de binding que le PDF de confirmation : `{ticket:uuid}` sans
            // scoping implicite, le contrôleur vérifie l'appartenance.
            Route::patch('{departure:uuid}/tickets/{ticket:uuid}/passenger', [OpsPassengerCorrectionController::class, 'update'])
                ->withoutScopedBindings()
                ->name('passenger-correction');

            // Progression du départ par le convoyeur (MEZ-98) : embarquement
            // commencé, bus parti, arrivé, retard. Même statut que OPS Web.
            Route::post('{departure:uuid}/status', [OpsDepartureProgressController::class, 'updateStatus'])->name('status');
            Route::patch('{departure:uuid}/delay', [OpsDepartureProgressController::class, 'recordDelay'])->name('delay');
        });

    // OPS Mobile — signalement d'incident par le convoyeur (MEZ-98). Même
    // garde que les autres routes terrain ; l'envoi est limité (anti-abus).
    Route::prefix('ops/incidents')
        ->name('ops.incidents.')
        ->middleware(['auth:sanctum', 'ops.session'])
        ->group(function (): void {
            Route::get('/', [OpsIncidentController::class, 'index'])->name('index');
            Route::post('/', [OpsIncidentController::class, 'store'])->middleware('throttle:10,1')->name('store');
            Route::get('{incident}', [OpsIncidentController::class, 'show'])->whereUuid('incident')->name('show');
        });

    // OPS Mobile — validation d'embarquement (MEZ-84 suite). Le scan lui
    // même exige un terminal actif ; l'annulation exceptionnelle est une
    // action de correction ops (permission dédiée) qui n'en a pas besoin.
    Route::prefix('ops/boarding')
        ->name('ops.boarding.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::post('/', [OpsBoardingController::class, 'store'])
                ->middleware('ops.session')
                ->name('store');
            Route::post('{boardingEvent:uuid}/reverse', [OpsBoardingController::class, 'reverse'])->name('reverse');

            // PDF de confirmation d'embarquement — mode téléphone (pas de
            // terminal P501, donc pas d'imprimante thermique). Voir ticket
            // "Supporter le scan par caméra téléphone en alternative au P501".
            // `withoutScopedBindings` : un paramètre enfant à clé custom
            // (`{ticket:uuid}`) déclenche sinon le scoping implicite, qui
            // résoudrait le billet via `$departure->tickets()` (relation qui
            // n'existe pas). Le contrôleur vérifie lui-même que le billet est
            // bien sur ce départ (422 `ticket_not_for_departure`).
            Route::get('{departure:uuid}/tickets/{ticket:uuid}/pdf', [OpsBoardingController::class, 'pdf'])
                ->withoutScopedBindings()
                ->middleware('ops.session')
                ->name('ticket-pdf');
        });

    Route::prefix('ops/seat-releases')
        ->name('ops.seat-releases.')
        ->middleware(['auth:sanctum', 'ops.session'])
        ->group(function (): void {
            Route::post('/', [OpsSeatReleaseController::class, 'store'])->name('store');
            Route::post('no-show-closure', [OpsSeatReleaseController::class, 'closeNoShows'])->name('no-show-closure');
        });

    Route::prefix('ops/sync')
        ->name('ops.sync.')
        ->middleware(['auth:sanctum', 'ops.session'])
        ->group(function (): void {
            Route::post('manifest-versions', [OpsSyncController::class, 'manifestVersions'])->name('manifest-versions');
            Route::post('batches', [OpsSyncController::class, 'submitBatch'])->name('batches');
        });

    // Chat support tawk.to de l'app convoyeur : session authentifiée seule
    // (pas `ops.session`) — l'aide doit rester joignable même si le terminal
    // n'est pas (encore) lié ou actif.
    Route::get('ops/support-chat-configuration', [OpsSupportChatConfigurationController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('ops.support-chat-configuration');

    Route::prefix('ops/tracking')
        ->name('ops.tracking.')
        ->middleware(['auth:sanctum', 'ops.session'])
        ->group(function (): void {
            Route::post('positions/batch', [OpsTrackingController::class, 'storeBatch'])
                ->middleware('throttle:tracking-batch')
                ->name('positions.batch');
        });

    Route::prefix('fleet/tracking')
        ->name('fleet.tracking.')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [FleetTrackingController::class, 'index'])->name('index');
        });

    Route::prefix('notifications')
        ->name('notifications.')
        ->middleware(['auth:sanctum', 'notifications.access'])
        ->group(function (): void {
            Route::get('/', [OpsNotificationController::class, 'index'])->name('index');
            Route::get('sent', [OpsNotificationController::class, 'sent'])->name('sent');
            Route::get('unread-count', [OpsNotificationController::class, 'unreadCount'])->name('unread-count');
            Route::get('preferences', [OpsNotificationPreferenceController::class, 'show'])->name('preferences.show');
            Route::put('preferences', [OpsNotificationPreferenceController::class, 'update'])->name('preferences.update');
            Route::post('read-all', [OpsNotificationController::class, 'markAllRead'])->name('read-all');
            Route::post('{notification}/read', [OpsNotificationController::class, 'markRead'])->name('read');
        });

    Route::get('notifications/web-push-configuration', [OpsWebPushPreferenceController::class, 'configuration'])
        ->middleware('auth:sanctum')
        ->name('notifications.web-push-configuration');

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
    Route::post('payments/webhook/kkiapay', [PaymentController::class, 'webhookKkiapay'])
        ->name('payments.webhook.kkiapay');

    // Provider callback endpoint (redirect or POST after payment processing).
    Route::match(['get', 'post'], 'payments/callback/fedapay', [PaymentController::class, 'callbackFedaPay'])
        ->name('payments.callback.fedapay');
});
