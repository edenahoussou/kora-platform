<?php

namespace App\Modules\Settings\Settings;

use Spatie\LaravelSettings\Settings;

final class IntegrationsSettings extends Settings
{
    public bool $firebasePushEnabled = false;

    public ?string $firebaseProjectId = null;

    public ?string $firebaseWebApiKey = null;

    public ?string $firebaseWebAuthDomain = null;

    public ?string $firebaseWebStorageBucket = null;

    public ?string $firebaseWebMessagingSenderId = null;

    public ?string $firebaseWebAppId = null;

    public ?string $firebaseAndroidAppId = null;

    public ?string $firebaseIosAppId = null;

    public ?string $firebaseWebVapidKey = null;

    public ?string $firebaseServiceAccountJson = null;

    public bool $emecfTestMode = true;

    public ?string $emecfToken = null;

    public string $emecfDefaultIfu = '0000000000000';

    public string $emecfTestInvoiceUrl = 'https://developper.impots.bj/sygmef-emcf/api/';

    public string $emecfTestInfoUrl = 'https://developper.impots.bj/sygmef-emcf/api/info';

    public string $emecfProductionInvoiceUrl = 'https://sygmef.impots.bj/emcf/api/';

    public string $emecfProductionInfoUrl = 'https://sygmef.impots.bj/emcf/api/info';

    public int $emecfHttpTimeout = 30;

    public int $emecfConnectTimeout = 10;

    public int $emecfRetry = 3;

    public int $emecfRetryDelay = 1000;

    public bool $emecfSaveInvoices = true;

    public bool $emecfSaveLogs = true;

    public string $emecfLogLevel = 'error';

    public int $emecfInvoiceSubmissionTimeout = 30;

    public int $emecfFinalizationTimeout = 30;

    public int $emecfStatusCheckTimeout = 10;

    public int $emecfInfoRequestTimeout = 10;

    public int $emecfMaxRetryAttempts = 3;

    public int $emecfRetryBackoffMultiplier = 2;

    public ?string $emecfNim = null;

    public ?string $emecfShopName = null;

    public ?string $emecfAccountStatus = null;

    public ?string $emecfAddress1 = null;

    public ?string $emecfAddress2 = null;

    public ?string $emecfAddress3 = null;

    public ?string $emecfContact1 = null;

    public ?string $emecfContact2 = null;

    public ?string $emecfContact3 = null;

    public ?string $emecfApiVersion = null;

    public ?string $emecfTokenValidUntil = null;

    public bool $fedapayEnabled = false;

    public ?string $fedapaySecretKey = null;

    public string $fedapayEnvironment = 'sandbox';

    public ?string $fedapayCallbackUrl = null;

    public ?string $fedapayWebhookSecret = null;

    public string $fedapayCurrency = 'XOF';

    public string $fedapayDefaultPhoneCountry = 'bj';

    public string $fedapayMtnMomoMode = 'mtn';

    public string $fedapayMoovFloozMode = 'moov';

    public string $fedapayCeltiisCashMode = 'celtiis';

    public bool $mtnMomoEnabled = false;

    public ?string $mtnMomoSubscriptionKey = null;

    public ?string $mtnMomoUserId = null;

    public ?string $mtnMomoApiKey = null;

    public string $mtnMomoEnvironment = 'sandbox';

    public ?string $mtnMomoCallbackUrl = null;

    public string $mtnMomoTargetEnvironment = 'mtnbenin';

    public string $mtnMomoSandboxCurrency = 'EUR';

    public string $mtnMomoProductionCurrency = 'XOF';

    public ?string $mtnMomoWebhookSecret = null;

    public string $mapProvider = 'openstreetmap';

    public ?string $mapGoogleApiKey = null;

    public string $mapOsmTileUrl = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    public string $mapOsmAttribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    public string $genukaUrl = 'https://wa.genuka.com';

    public ?string $genukaToken = null;

    public ?string $genukaConnectionId = null;

    public string $wahaUrl = 'http://waha:3000';

    public ?string $wahaToken = null;

    public string $wahaSession = 'default';

    public int $wahaNewContactDelaySeconds = 30;

    public ?string $googleOauthClientId = null;

    public ?string $googleOauthClientSecret = null;

    public string $googleOauthRedirect = '/api/v1/auth/oauth/google/callback';

    public ?string $facebookOauthClientId = null;

    public ?string $facebookOauthClientSecret = null;

    public string $facebookOauthRedirect = '/api/v1/auth/oauth/facebook/callback';

    public ?string $tiktokOauthClientId = null;

    public ?string $tiktokOauthClientSecret = null;

    public string $tiktokOauthRedirect = '/api/v1/auth/oauth/tiktok/callback';

    public static function group(): string
    {
        return 'integrations';
    }

    /** @return list<string> */
    public static function encrypted(): array
    {
        return [
            'firebaseServiceAccountJson',
            'emecfToken',
            'fedapaySecretKey',
            'fedapayWebhookSecret',
            'mtnMomoSubscriptionKey',
            'mtnMomoUserId',
            'mtnMomoApiKey',
            'mtnMomoWebhookSecret',
            'mapGoogleApiKey',
            'genukaToken',
            'genukaConnectionId',
            'wahaToken',
            'googleOauthClientSecret',
            'facebookOauthClientSecret',
            'tiktokOauthClientSecret',
        ];
    }
}
