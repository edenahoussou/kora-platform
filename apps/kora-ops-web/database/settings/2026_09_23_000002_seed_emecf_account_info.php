<?php

use App\Modules\Settings\Settings\IntegrationsSettings;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** @var list<string> */
    private const ACCOUNT_PROPERTIES = [
        'emecfNim',
        'emecfShopName',
        'emecfAccountStatus',
        'emecfAddress1',
        'emecfAddress2',
        'emecfAddress3',
        'emecfContact1',
        'emecfContact2',
        'emecfContact3',
        'emecfApiVersion',
        'emecfTokenValidUntil',
    ];

    public function up(): void
    {
        $this->migrator->inGroup('integrations', function ($settings): void {
            foreach (self::ACCOUNT_PROPERTIES as $property) {
                $settings->add($property, null);
            }
        });

        if (app()->runningUnitTests()) {
            return;
        }

        $this->seedFromDgi();
    }

    /**
     * Pulls the taxpayer account details from the e-MECeF information API so
     * invoice views can display the values exactly as registered at the DGI.
     * Silently skipped when the token or the endpoint is not reachable yet.
     */
    private function seedFromDgi(): void
    {
        try {
            $settings = app(IntegrationsSettings::class);

            if (blank($settings->emecfToken)) {
                return;
            }

            $base = $settings->emecfTestMode
                ? $settings->emecfTestInfoUrl
                : $settings->emecfProductionInfoUrl;

            $base = rtrim((string) $base, '/');

            if (! str_ends_with($base, '/info')) {
                $base .= '/info';
            }

            $response = Http::acceptJson()
                ->withToken((string) $settings->emecfToken)
                ->timeout(8)
                ->connectTimeout(5)
                ->get($base.'/status');

            if (! $response->successful()) {
                return;
            }

            $payload = (array) $response->json();
            $emcf = (array) ($payload['emcfList'][0] ?? []);

            $this->migrator->inGroup('integrations', function ($settings) use ($payload, $emcf): void {
                $settings->update('emecfNim', fn () => $emcf['nim'] ?? ($payload['nim'] ?? null));
                $settings->update('emecfShopName', fn () => $emcf['shopName'] ?? null);
                $settings->update('emecfAccountStatus', fn () => $emcf['status'] ?? null);
                $settings->update('emecfAddress1', fn () => $emcf['address1'] ?? null);
                $settings->update('emecfAddress2', fn () => $emcf['address2'] ?? null);
                $settings->update('emecfAddress3', fn () => $emcf['address3'] ?? null);
                $settings->update('emecfContact1', fn () => $emcf['contact1'] ?? null);
                $settings->update('emecfContact2', fn () => $emcf['contact2'] ?? null);
                $settings->update('emecfContact3', fn () => $emcf['contact3'] ?? null);
                $settings->update('emecfApiVersion', fn () => isset($payload['version']) ? (string) $payload['version'] : null);
                $settings->update('emecfTokenValidUntil', fn () => $payload['tokenValid'] ?? null);
                $settings->update('emecfDefaultIfu', fn ($value) => ($value === '0000000000000' || blank($value)) && filled($payload['ifu'] ?? null)
                    ? (string) $payload['ifu']
                    : $value);
            });
        } catch (Throwable) {
            // The DGI endpoint must never block a deployment.
        }
    }
};
