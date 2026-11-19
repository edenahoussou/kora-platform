<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operations.parcelIntakeExpiryHours', 48);
        $this->migrator->add('operations.parcelFirstReminderDays', 3);
        $this->migrator->add('operations.parcelSecondReminderDays', 7);
        $this->migrator->add('operations.parcelReturnDays', 30);
        $this->migrator->add('operations.parcelHandoffMaxAttempts', 5);
        $this->migrator->add('operations.parcelFiscalAlertHours', 20);
    }
};
