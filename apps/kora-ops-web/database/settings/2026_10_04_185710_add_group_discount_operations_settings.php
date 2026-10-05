<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('operations', function ($settings): void {
            $settings->add('groupDiscountEnabled', true);
            $settings->add('groupDiscountPassengerThreshold', 10);
            $settings->add('groupDiscountPercentage', 10.0);
        });
    }
};
