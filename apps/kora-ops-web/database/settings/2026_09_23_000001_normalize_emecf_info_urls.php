<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            foreach (['emecfTestInfoUrl', 'emecfProductionInfoUrl'] as $property) {
                $settings->update($property, function ($value) {
                    $url = rtrim((string) $value, '/');

                    return str_ends_with($url, '/info') ? $url : $url.'/info';
                });
            }
        });
    }
};
