<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('operations', function ($settings): void {
            $settings->add('passengerDepartureReminderHours', [24, 2]);
            $settings->add('passengerDelayThresholdMinutes', 1);
        });
    }
};
