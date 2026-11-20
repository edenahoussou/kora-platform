<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operations.parcelMinimumWeightGrams', 1);
        $this->migrator->add('operations.parcelMaximumWeightGrams', 30000);
        $this->migrator->add('operations.parcelCashDeclarationThresholdFcfa', 500000);
    }
};
