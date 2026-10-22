<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            $settings->add('kkiapayEnabled', false);
            $settings->add('kkiapayPublicKey', null);
            $settings->addEncrypted('kkiapayPrivateKey', null);
            $settings->addEncrypted('kkiapaySecretKey', null);
            $settings->add('kkiapayEnvironment', 'sandbox');
            $settings->add('kkiapayCallbackUrl', null);
            $settings->addEncrypted('kkiapayWebhookSecret', null);
            $settings->add('kkiapayCountries', ['BJ', 'TG', 'CI']);
            $settings->add('fedapayMtnMomoCiMode', 'mtn_ci');
            $settings->add('fedapayMoovFloozCiMode', 'moov_ci');
            $settings->add('fedapayWaveCiMode', 'wave_ci');
            $settings->add('fedapayOrangeMoneyCiMode', 'orange_ci');
            $settings->add('fedapayMoovFloozTgMode', 'moov_tg');
            $settings->add('fedapayTmoneyTgMode', 'tmoney');
        });
    }
};
