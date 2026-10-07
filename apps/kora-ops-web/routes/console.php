<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bus:expire-holds')->everyMinute()->withoutOverlapping();
Schedule::command('bus:expire-assisted-bookings')->everyMinute()->withoutOverlapping();
Schedule::command('bus:flag-stale-departures')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('bus:maintain-programmes')->hourly()->withoutOverlapping();
Schedule::command('companies:billing-statements')->monthlyOn(1, '03:15')->withoutOverlapping();
Schedule::command('whatsapp:retry-skipped --hours=2 --limit=50')->hourly()->withoutOverlapping();
