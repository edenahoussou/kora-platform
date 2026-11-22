<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('general', function ($settings): void {
            $settings->add('displayName', (string) config('app.name', 'KORA BUS'));
            $settings->add('locale', (string) config('app.locale', 'fr'));
            $settings->add('fallbackLocale', (string) config('app.fallback_locale', 'fr'));
            $settings->add('timezone', 'Africa/Porto-Novo');
            $settings->add('currency', 'XOF');
            $settings->add('supportCallPhone', config('kora.assisted_bookings.support.call_phone'));
            $settings->add('supportWhatsappPhone', config('kora.assisted_bookings.support.whatsapp_phone'));
        });

        $this->migrator->inGroup('operations', function ($settings): void {
            $settings->add('quoteTtlMinutes', (int) config('kora.bookings.quote_ttl_minutes', 10));
            $settings->add('saleCutoffMinutes', (int) config('kora.departures.sellability.default_cutoff_minutes', 30));
            $settings->add('saleCutoffOverrides', (array) config('kora.departures.sellability.overrides.lines', []));
            $settings->add('paymentTimeoutMinutes', (int) config('payments.expiration.payment_timeout_minutes', 15));
            $settings->add('staleDepartureHours', (int) config('kora.departures.sellability.stale_threshold_hours', 24));
            $settings->add('maxDepartureDelayMinutes', (int) config('kora.departures.sellability.max_delay_minutes', 720));
            $settings->add('connectionMarginMinutes', (int) config('kora.itineraries.connection_margin_minutes', 45));
            $settings->add('roundTripMarginMinutes', (int) config('kora.itineraries.round_trip_margin_minutes', 0));
            $settings->add('maxLegs', (int) config('kora.itineraries.max_legs', 3));
            $settings->add('maxResults', (int) config('kora.itineraries.max_results', 25));
            $settings->add('defaultSearchDays', (int) config('kora.itineraries.default_search_days', 7));
            $settings->add('maxWaitHours', (int) config('kora.itineraries.max_wait_hours', 12));
            $settings->add('opsSessionTtlMinutes', (int) config('ops_session.ttl_minutes', 720));
            $settings->add('opsSessionMaxLifetimeMinutes', (int) config('ops_session.max_lifetime_minutes', 1440));
            $settings->add('opsSessionTouchIntervalSeconds', (int) config('ops_session.touch_interval_seconds', 60));
            $settings->add('notificationsMailEnabled', (bool) config('ops_notifications.mail.enabled', true));
            $settings->add('notificationsMailTries', (int) config('ops_notifications.mail.tries', 3));
            $settings->add('manifestStaleDebounceSeconds', (int) config('ops_notifications.manifest_stale_debounce_seconds', 120));
        });

        $mapsProvider = DB::table('map_settings')->value('provider')
            ?: config('services.maps.default_provider', 'openstreetmap');

        $this->migrator->inGroup('integrations', function ($settings) use ($mapsProvider): void {
            $emecf = config('emecf', []);
            $emecfUrls = $emecf['urls'] ?? [];
            $emecfHttp = $emecf['http'] ?? [];
            $emecfDatabase = $emecf['database'] ?? [];
            $emecfTimeouts = $emecf['timeouts'] ?? [];
            $emecfRetry = $emecf['retry'] ?? [];
            $fedapay = config('payments.providers.fedapay', []);
            $mtn = config('payments.providers.mtn_momo', []);
            $maps = config('services.maps', []);
            $genuka = config('services.genuka', []);
            $waha = config('services.waha', []);

            $settings->add('emecfTestMode', (bool) ($emecf['test_mode'] ?? true));
            $settings->addEncrypted('emecfToken', $emecf['token'] ?? null);
            $settings->add('emecfDefaultIfu', (string) ($emecf['default_ifu'] ?? '0000000000000'));
            $settings->add('emecfTestInvoiceUrl', (string) ($emecfUrls['test']['invoice'] ?? ''));
            $settings->add('emecfTestInfoUrl', (string) ($emecfUrls['test']['info'] ?? ''));
            $settings->add('emecfProductionInvoiceUrl', (string) ($emecfUrls['production']['invoice'] ?? ''));
            $settings->add('emecfProductionInfoUrl', (string) ($emecfUrls['production']['info'] ?? ''));
            $settings->add('emecfHttpTimeout', (int) ($emecfHttp['timeout'] ?? 30));
            $settings->add('emecfConnectTimeout', (int) ($emecfHttp['connect_timeout'] ?? 10));
            $settings->add('emecfRetry', (int) ($emecfHttp['retry'] ?? 3));
            $settings->add('emecfRetryDelay', (int) ($emecfHttp['retry_delay'] ?? 1000));
            $settings->add('emecfSaveInvoices', (bool) ($emecfDatabase['save_invoices'] ?? true));
            $settings->add('emecfSaveLogs', (bool) ($emecfDatabase['save_logs'] ?? true));
            $settings->add('emecfLogLevel', (string) ($emecfDatabase['log_level'] ?? 'error'));
            $settings->add('emecfInvoiceSubmissionTimeout', (int) ($emecfTimeouts['invoice_submission'] ?? 30));
            $settings->add('emecfFinalizationTimeout', (int) ($emecfTimeouts['finalization'] ?? 30));
            $settings->add('emecfStatusCheckTimeout', (int) ($emecfTimeouts['status_check'] ?? 10));
            $settings->add('emecfInfoRequestTimeout', (int) ($emecfTimeouts['info_request'] ?? 10));
            $settings->add('emecfMaxRetryAttempts', (int) ($emecfRetry['max_attempts'] ?? 3));
            $settings->add('emecfRetryBackoffMultiplier', (int) ($emecfRetry['backoff_multiplier'] ?? 2));

            $settings->add('fedapayEnabled', (bool) ($fedapay['enabled'] ?? false));
            $settings->addEncrypted('fedapaySecretKey', $fedapay['secret_key'] ?? null);
            $settings->add('fedapayEnvironment', (string) ($fedapay['environment'] ?? 'sandbox'));
            $settings->add('fedapayCallbackUrl', $fedapay['callback_url'] ?? null);
            $settings->addEncrypted('fedapayWebhookSecret', $fedapay['webhook_secret'] ?? null);
            $settings->add('fedapayCurrency', (string) ($fedapay['currency'] ?? 'XOF'));
            $settings->add('fedapayDefaultPhoneCountry', (string) ($fedapay['default_phone_country'] ?? 'bj'));
            $settings->add('fedapayMtnMomoMode', (string) ($fedapay['mobile_money_modes']['mtn_momo'] ?? 'mtn'));
            $settings->add('fedapayMoovFloozMode', (string) ($fedapay['mobile_money_modes']['moov_flooz'] ?? 'moov'));
            $settings->add('fedapayCeltiisCashMode', (string) ($fedapay['mobile_money_modes']['celtiis_cash'] ?? 'celtiis'));

            $settings->add('mtnMomoEnabled', (bool) ($mtn['enabled'] ?? false));
            $settings->addEncrypted('mtnMomoSubscriptionKey', $mtn['subscription_key'] ?? null);
            $settings->addEncrypted('mtnMomoUserId', $mtn['user_id'] ?? null);
            $settings->addEncrypted('mtnMomoApiKey', $mtn['api_key'] ?? null);
            $settings->add('mtnMomoEnvironment', (string) ($mtn['environment'] ?? 'sandbox'));
            $settings->add('mtnMomoCallbackUrl', $mtn['callback_url'] ?? null);
            $settings->add('mtnMomoTargetEnvironment', (string) ($mtn['target_environment'] ?? 'sandbox'));
            $settings->add('mtnMomoSandboxCurrency', (string) ($mtn['sandbox_currency'] ?? 'EUR'));
            $settings->add('mtnMomoProductionCurrency', (string) ($mtn['production_currency'] ?? 'XOF'));
            $settings->addEncrypted('mtnMomoWebhookSecret', $mtn['webhook_secret'] ?? null);

            $settings->add('mapProvider', (string) $mapsProvider);
            $settings->addEncrypted('mapGoogleApiKey', $maps['google']['api_key'] ?? null);
            $settings->add('mapOsmTileUrl', (string) ($maps['osm']['tile_url'] ?? ''));
            $settings->add('mapOsmAttribution', (string) ($maps['osm']['attribution'] ?? ''));

            $settings->add('genukaUrl', (string) ($genuka['url'] ?? 'https://wa.genuka.com'));
            $settings->addEncrypted('genukaToken', $genuka['token'] ?? null);
            $settings->addEncrypted('genukaConnectionId', $genuka['connection_id'] ?? null);
            $settings->add('wahaUrl', (string) ($waha['url'] ?? 'http://waha:3000'));
            $settings->addEncrypted('wahaToken', $waha['token'] ?? null);
            $settings->add('wahaSession', (string) ($waha['session'] ?? 'default'));
            $settings->add('wahaNewContactDelaySeconds', (int) ($waha['new_contact_delay_seconds'] ?? 30));

            $settings->add('googleOauthClientId', config('services.google.client_id'));
            $settings->addEncrypted('googleOauthClientSecret', config('services.google.client_secret'));
            $settings->add('googleOauthRedirect', (string) config('services.google.redirect', '/api/v1/auth/oauth/google/callback'));
            $settings->add('facebookOauthClientId', config('services.facebook.client_id'));
            $settings->addEncrypted('facebookOauthClientSecret', config('services.facebook.client_secret'));
            $settings->add('facebookOauthRedirect', (string) config('services.facebook.redirect', '/api/v1/auth/oauth/facebook/callback'));
            $settings->add('tiktokOauthClientId', config('services.tiktok.client_id'));
            $settings->addEncrypted('tiktokOauthClientSecret', config('services.tiktok.client_secret'));
            $settings->add('tiktokOauthRedirect', (string) config('services.tiktok.redirect', '/api/v1/auth/oauth/tiktok/callback'));
        });
    }

    public function down(): void
    {
        DB::table(config('settings.repositories.database.table') ?? 'settings')
            ->whereIn('group', ['general', 'operations', 'integrations'])
            ->delete();
    }
};
