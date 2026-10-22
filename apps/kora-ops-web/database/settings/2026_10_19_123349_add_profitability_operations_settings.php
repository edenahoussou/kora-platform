<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('operations', function ($settings): void {
            $settings->add('profitabilityGpsMinCoveragePercent', 70);
            $settings->add('profitabilityGpsMaxGapMinutes', 15);
            $settings->add('profitabilityGpsMaxSpeedKmh', 140);
        });
    }
};
