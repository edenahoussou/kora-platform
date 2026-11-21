<?php

use App\Modules\Fiscalization\Actions\InitiateCreditNoteAction;
use App\Modules\Notifications\Services\Sms\ConexteoSmsClient;
use App\Modules\Notifications\Services\WhatsApp\WhatsAppMessageDispatcher;
use App\Modules\Parcels\Services\ParcelFinance;
use App\Modules\Parcels\Services\ParcelHandoff;
use App\Modules\Parcels\Services\ParcelNotifications;
use App\Modules\Parcels\Services\ParcelPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bus:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('bus:expire-assisted-bookings')->everyMinute()->withoutOverlapping();
Schedule::command('bus:flag-stale-departures')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('cash:flag-unclosed-sessions')->dailyAt('06:30')->withoutOverlapping();
Schedule::command('bus:maintain-programmes')->hourly()->withoutOverlapping();
Schedule::command('companies:billing-statements')->monthlyOn(1, '03:15')->withoutOverlapping();
Schedule::command('whatsapp:retry-skipped --hours=2 --limit=50')->hourly()->withoutOverlapping();
Schedule::command('health:check')->everyMinute()->withoutOverlapping();
Schedule::command('health:schedule-check-heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('health:queue-check-heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('kora-points:check-settlements')->hourly()->withoutOverlapping()->onOneServer();

Schedule::command('wallet:release-expired-holds')->everyMinute()->withoutOverlapping();
Schedule::command('wallet:credit-pending-legs')->dailyAt('03:10')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:expire-points')->dailyAt('03:20')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:expiry-reminders')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:anniversary-bonuses')->dailyAt('08:10')->withoutOverlapping()->onOneServer();
Schedule::command('wallet:loyalty-rules:activate-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('wallet:reconcile-topups')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('payments:reconcile-mtn-momo')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::call(function (): void {
    app(ParcelHandoff::class)->advanceDueParcels();
})->name('parcels:advance-due')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::call(function (): void {
    app(ParcelNotifications::class)->sendPending(
        app(WhatsAppMessageDispatcher::class),
        app(ConexteoSmsClient::class),
    );
})->name('parcels:notify')->everyMinute()->withoutOverlapping()->onOneServer();

Schedule::call(function (): void {
    app(ParcelFinance::class)->retryFiscalDocuments(
        app(InitiateCreditNoteAction::class),
    );
})->name('parcels:fiscalization')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

Schedule::call(function (): void {
    app(ParcelPayments::class)->expirePending();
})->name('parcels:expire-payments')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
