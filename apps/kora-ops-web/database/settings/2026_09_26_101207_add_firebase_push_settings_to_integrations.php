<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            $settings->add('firebasePushEnabled', false);
            $settings->add('firebaseProjectId', null);
            $settings->add('firebaseWebApiKey', null);
            $settings->add('firebaseWebAuthDomain', null);
            $settings->add('firebaseWebStorageBucket', null);
            $settings->add('firebaseWebMessagingSenderId', null);
            $settings->add('firebaseWebAppId', null);
            $settings->add('firebaseWebVapidKey', null);
            $settings->addEncrypted('firebaseServiceAccountJson', null);
        });
    }
};
