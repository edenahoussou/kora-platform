<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('operations.koraPointCommissionBasisPoints', null);
        $this->migrator->add('operations.koraPointCeilingAmount', null);
        $this->migrator->add('operations.koraPointSettlementCalendar', null);
        $this->migrator->add('operations.koraPointGraceDays', 0);
    }
};
