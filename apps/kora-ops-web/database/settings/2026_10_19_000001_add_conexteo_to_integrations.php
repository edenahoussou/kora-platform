<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            $settings->add('conexteoEnabled', false);
            $settings->add('conexteoAppId', null);
            $settings->add('conexteoSender', 'KoraExpress');
            $settings->addEncrypted('conexteoApiKey', null);
        });
    }
};
