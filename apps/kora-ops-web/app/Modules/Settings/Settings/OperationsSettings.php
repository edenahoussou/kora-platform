<?php

namespace App\Modules\Settings\Settings;

use Spatie\LaravelSettings\Settings;

final class OperationsSettings extends Settings
{
    public ?int $koraPointCommissionBasisPoints = null;

    public ?int $koraPointCeilingAmount = null;

    /** @phpstan-var array<string, mixed>|null */
    public ?array $koraPointSettlementCalendar = null;

    public int $koraPointGraceDays = 0;

    public int $quoteTtlMinutes = 10;

    public int $seatHoldMinutes = 10;

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

    public int $profitabilityGpsMinCoveragePercent = 70;

    public int $profitabilityGpsMaxGapMinutes = 15;

    public int $profitabilityGpsMaxSpeedKmh = 140;

    public bool $groupDiscountEnabled = true;

    public int $groupDiscountPassengerThreshold = 10;

    public float $groupDiscountPercentage = 10.0;

    public int $parcelIntakeExpiryHours = 48;

    public int $parcelMinimumWeightGrams = 1;

    public int $parcelMaximumWeightGrams = 30000;

    public int $parcelCashDeclarationThresholdFcfa = 500000;

    public int $parcelFirstReminderDays = 3;

    public int $parcelSecondReminderDays = 7;

    public int $parcelReturnDays = 30;

    public int $parcelHandoffMaxAttempts = 5;

    public int $parcelFiscalAlertHours = 20;

    public int $parcelRoutingHorizonDays = 30;

    public bool $parcelIdentityOverrideEnabled = true;

    public static function group(): string
    {
        return 'operations';
    }
}
