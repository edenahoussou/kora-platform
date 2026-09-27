<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('operations', function ($settings): void {
            $settings->add('trackingStaleAfterSeconds', 90);
            $settings->add('trackingTerminalOfflineAfterSeconds', 300);
            $settings->add('trackingDegradedAccuracyMeters', 75);
            $settings->add('trackingRouteCorridorMeters', 1000);
            $settings->add('trackingRecentPositionsLimit', 60);
            $settings->add('trackingMaxClockSkewSeconds', 120);
            $settings->add('trackingMaxPositionAgeMinutes', 180);
            $settings->add('trackingMaxPositionsPerBatch', 200);
            $settings->add('trackingCacheTtlSeconds', 600);
        });
    }
};
