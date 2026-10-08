<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            $settings->add('tawktoEnabled', false);
            $settings->add('tawktoPropertyId', null);
            $settings->add('tawktoWidgetId', 'default');
        });
    }
};
