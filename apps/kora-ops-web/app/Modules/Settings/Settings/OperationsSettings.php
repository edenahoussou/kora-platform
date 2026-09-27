<?php

namespace App\Modules\Settings\Settings;

use Spatie\LaravelSettings\Settings;

final class OperationsSettings extends Settings
{
    public int $quoteTtlMinutes = 10;

    public int $saleCutoffMinutes = 30;

    /** @var array<string, int> */
    public array $saleCutoffOverrides = [];

    public int $paymentTimeoutMinutes = 15;

    public int $staleDepartureHours = 24;

    public int $maxDepartureDelayMinutes = 720;

    public int $connectionMarginMinutes = 45;

    public int $roundTripMarginMinutes = 0;

    public int $maxLegs = 3;

    public int $maxResults = 25;

    public int $defaultSearchDays = 7;

    public int $maxWaitHours = 12;

    public int $opsSessionTtlMinutes = 720;

    public int $opsSessionMaxLifetimeMinutes = 1440;

    public int $opsSessionTouchIntervalSeconds = 60;

    public bool $notificationsMailEnabled = true;

    public int $notificationsMailTries = 3;

    public int $manifestStaleDebounceSeconds = 120;

    /** @var array<int, int> */
    public array $passengerDepartureReminderHours = [24, 2];

    public int $passengerDelayThresholdMinutes = 1;

    public int $trackingStaleAfterSeconds = 90;

    public int $trackingTerminalOfflineAfterSeconds = 300;

    public int $trackingDegradedAccuracyMeters = 75;

    public int $trackingRouteCorridorMeters = 1000;

    public int $trackingRecentPositionsLimit = 60;

    public int $trackingMaxClockSkewSeconds = 120;

    public int $trackingMaxPositionAgeMinutes = 180;

    public int $trackingMaxPositionsPerBatch = 200;

    public int $trackingCacheTtlSeconds = 600;

    public static function group(): string
    {
        return 'operations';
    }
}
