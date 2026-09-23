<?php

namespace App\Modules\Settings\Services;

use App\Models\User;
use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Data\SettingsSnapshot;
use App\Modules\Settings\DTO\SettingsUpdate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use Spatie\LaravelSettings\Settings;
use Spatie\LaravelSettings\Support\SettingsCacheFactory;

use function Illuminate\Support\enum_value;

final class DatabaseSettingsCoordinator implements SettingsCoordinator
{
    public function __construct(private readonly DatabaseManager $database) {}

    public function snapshot(SettingsGroup $group): SettingsSnapshot
    {
        $actor = Auth::user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('An authenticated actor is required to read settings.');
        }

        $this->authorize($group->viewPermission(), $actor);

        $settings = $this->settings($group);

        return SettingsSnapshot::fromSettings(
            $group,
            $settings,
            $this->sensitiveProperties($settings),
        );
    }

    public function update(
        SettingsGroup $group,
        SettingsUpdate $update,
        User $actor,
    ): SettingsSnapshot {
        if ($update->group() !== $group) {
            throw new \InvalidArgumentException('The settings update does not belong to the selected group.');
        }

        $this->authorize($group->managePermission(), $actor);

        /** @var SettingsSnapshot $snapshot */
        $snapshot = $this->database->transaction(function () use ($group, $update, $actor): SettingsSnapshot {
            $this->lockGroup($group);

            // Evict before resolving the settings object so an enabled cache
            // cannot rehydrate a stale snapshot after the SQL lock is held.
            $this->clearCache($group);
            $settings = $this->settings($group);

            $before = $settings->toArray();
            $sensitive = $this->sensitiveProperties($settings);
            $values = $this->normalize($group, $update->values(), $before, $sensitive, $update->clearSecrets());

            if ($values === $before) {
                return SettingsSnapshot::fromSettings($group, $settings, $sensitive);
            }

            $settings->fill($values)->save();
            app(SettingsCacheFactory::class)->build($settings::repository())->clear();

            $this->recordAudit($group, $actor, $before, $values, $sensitive);

            return SettingsSnapshot::fromSettings($group, $settings->refresh(), $sensitive);
        });

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $submitted
     * @param  array<string, mixed>  $before
     * @param  list<string>  $sensitive
     * @param  list<string>  $clearSecrets
     * @return array<string, mixed>
     */
    private function normalize(
        SettingsGroup $group,
        array $submitted,
        array $before,
        array $sensitive,
        array $clearSecrets,
    ): array {
        $class = $group->settingsClass();
        $allowed = array_keys((new ReflectionClass($class))->getDefaultProperties());
        $unknown = array_diff(array_keys($submitted), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'settings' => ['Unknown settings: '.implode(', ', $unknown)],
            ]);
        }

        $invalidClear = array_diff($clearSecrets, $sensitive);

        if ($invalidClear !== []) {
            throw ValidationException::withMessages([
                'clearSecrets' => ['Only sensitive integration settings may be cleared.'],
            ]);
        }

        $merged = $before;

        foreach ($submitted as $name => $value) {
            if (in_array($name, $sensitive, true) && blank($value) && ! in_array($name, $clearSecrets, true)) {
                continue;
            }

            $merged[$name] = $value;
        }

        foreach ($clearSecrets as $name) {
            $merged[$name] = null;
        }

        if ($group === SettingsGroup::Integrations && array_key_exists('mapOsmAttribution', $merged)) {
            // Leaflet renders attribution as HTML; persist plain text so a
            // settings manager cannot inject markup into every map viewer.
            $merged['mapOsmAttribution'] = trim(strip_tags((string) $merged['mapOsmAttribution']));
        }

        $validator = Validator::make($merged, $this->rules($group));
        $validator->after(function ($validator) use ($group, $merged): void {
            foreach ($this->dependencyErrors($group, $merged) as $field => $message) {
                $validator->errors()->add($field, $message);
            }
        });

        $validated = $validator->validate();

        return array_replace($before, $validated);
    }

    /** @return array<string, array<int, string>> */
    private function rules(SettingsGroup $group): array
    {
        if ($group === SettingsGroup::General) {
            return [
                'displayName' => ['required', 'string', 'max:120'],
                'locale' => ['required', 'regex:/^[a-z]{2}(?:[-_][A-Z]{2})?$/'],
                'fallbackLocale' => ['required', 'regex:/^[a-z]{2}(?:[-_][A-Z]{2})?$/'],
                'timezone' => ['required', 'timezone'],
                'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
                'supportCallPhone' => ['nullable', 'regex:/^\\+?[0-9().\\s-]{7,32}$/'],
                'supportWhatsappPhone' => ['nullable', 'regex:/^\\+?[0-9().\\s-]{7,32}$/'],
            ];
        }

        if ($group === SettingsGroup::Operations) {
            return [
                'quoteTtlMinutes' => ['required', 'integer', 'min:1', 'max:10080'],
                'saleCutoffMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
                // An empty override map is a valid, intentional state. The
                // coordinator merges partial updates with the persisted
                // snapshot before validation, so the array itself only needs
                // to keep its shape here.
                'saleCutoffOverrides' => ['array'],
                'paymentTimeoutMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
                'staleDepartureHours' => ['required', 'integer', 'min:1', 'max:8760'],
                'maxDepartureDelayMinutes' => ['required', 'integer', 'min:0', 'max:10080'],
                'connectionMarginMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'roundTripMarginMinutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'maxLegs' => ['required', 'integer', 'min:1', 'max:10'],
                'maxResults' => ['required', 'integer', 'min:1', 'max:500'],
                'defaultSearchDays' => ['required', 'integer', 'min:1', 'max:365'],
                'maxWaitHours' => ['required', 'integer', 'min:0', 'max:168'],
                'opsSessionTtlMinutes' => ['required', 'integer', 'min:1', 'max:43200'],
                'opsSessionMaxLifetimeMinutes' => ['required', 'integer', 'min:1', 'max:129600'],
                'opsSessionTouchIntervalSeconds' => ['required', 'integer', 'min:1', 'max:3600'],
                'notificationsMailEnabled' => ['required', 'boolean'],
                'notificationsMailTries' => ['required', 'integer', 'min:1', 'max:10'],
                'manifestStaleDebounceSeconds' => ['required', 'integer', 'min:0', 'max:86400'],
            ];
        }

        return [
            'emecfTestMode' => ['required', 'boolean'],
            'emecfToken' => ['nullable', 'string', 'max:500'],
            'emecfDefaultIfu' => ['required', 'string', 'max:32'],
            'emecfTestInvoiceUrl' => ['required', 'url:http,https', 'max:500'],
            'emecfTestInfoUrl' => ['required', 'url:http,https', 'max:500'],
            'emecfProductionInvoiceUrl' => ['required', 'url:http,https', 'max:500'],
            'emecfProductionInfoUrl' => ['required', 'url:http,https', 'max:500'],
            'emecfHttpTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfConnectTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfRetry' => ['required', 'integer', 'min:0', 'max:10'],
            'emecfRetryDelay' => ['required', 'integer', 'min:0', 'max:60000'],
            'emecfSaveInvoices' => ['required', 'boolean'],
            'emecfSaveLogs' => ['required', 'boolean'],
            'emecfLogLevel' => ['required', 'in:debug,info,notice,warning,error,critical,alert,emergency'],
            'emecfInvoiceSubmissionTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfFinalizationTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfStatusCheckTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfInfoRequestTimeout' => ['required', 'integer', 'min:1', 'max:300'],
            'emecfMaxRetryAttempts' => ['required', 'integer', 'min:0', 'max:10'],
            'emecfRetryBackoffMultiplier' => ['required', 'integer', 'min:1', 'max:10'],
            'emecfNim' => ['nullable', 'string', 'max:32'],
            'emecfShopName' => ['nullable', 'string', 'max:150'],
            'emecfAccountStatus' => ['nullable', 'string', 'max:32'],
            'emecfAddress1' => ['nullable', 'string', 'max:500'],
            'emecfAddress2' => ['nullable', 'string', 'max:500'],
            'emecfAddress3' => ['nullable', 'string', 'max:500'],
            'emecfContact1' => ['nullable', 'string', 'max:150'],
            'emecfContact2' => ['nullable', 'string', 'max:150'],
            'emecfContact3' => ['nullable', 'string', 'max:150'],
            'emecfApiVersion' => ['nullable', 'string', 'max:16'],
            'emecfTokenValidUntil' => ['nullable', 'string', 'max:64'],
            'fedapayEnabled' => ['required', 'boolean'],
            'fedapaySecretKey' => ['nullable', 'string', 'max:500'],
            'fedapayEnvironment' => ['required', 'in:sandbox,live'],
            'fedapayCallbackUrl' => ['nullable', 'url:http,https', 'max:500'],
            'fedapayWebhookSecret' => ['nullable', 'string', 'max:500'],
            'fedapayCurrency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'fedapayDefaultPhoneCountry' => ['required', 'regex:/^[a-z]{2}$/'],
            'fedapayMtnMomoMode' => ['required', 'string', 'max:32'],
            'fedapayMoovFloozMode' => ['required', 'string', 'max:32'],
            'fedapayCeltiisCashMode' => ['required', 'string', 'max:32'],
            'mtnMomoEnabled' => ['required', 'boolean'],
            'mtnMomoSubscriptionKey' => ['nullable', 'string', 'max:500'],
            'mtnMomoUserId' => ['nullable', 'string', 'max:500'],
            'mtnMomoApiKey' => ['nullable', 'string', 'max:500'],
            'mtnMomoEnvironment' => ['required', 'in:sandbox,production'],
            'mtnMomoCallbackUrl' => ['nullable', 'url:http,https', 'max:500'],
            'mtnMomoTargetEnvironment' => ['required', 'string', 'max:64'],
            'mtnMomoSandboxCurrency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'mtnMomoProductionCurrency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'mtnMomoWebhookSecret' => ['nullable', 'string', 'max:500'],
            'mapProvider' => ['required', 'in:openstreetmap,google'],
            'mapGoogleApiKey' => ['nullable', 'string', 'max:500'],
            'mapOsmTileUrl' => ['required', 'regex:/^https?:\\/\\/.+/', 'max:500'],
            'mapOsmAttribution' => ['required', 'string', 'max:1000'],
            'genukaUrl' => ['required', 'url:http,https', 'max:500'],
            'genukaToken' => ['nullable', 'string', 'max:500'],
            'genukaConnectionId' => ['nullable', 'string', 'max:500'],
            'wahaUrl' => ['required', 'url:http,https', 'max:500'],
            'wahaToken' => ['nullable', 'string', 'max:500'],
            'wahaSession' => ['required', 'string', 'max:120'],
            'wahaNewContactDelaySeconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'googleOauthClientId' => ['nullable', 'string', 'max:500'],
            'googleOauthClientSecret' => ['nullable', 'string', 'max:500'],
            'googleOauthRedirect' => ['required', 'string', 'max:500'],
            'facebookOauthClientId' => ['nullable', 'string', 'max:500'],
            'facebookOauthClientSecret' => ['nullable', 'string', 'max:500'],
            'facebookOauthRedirect' => ['required', 'string', 'max:500'],
            'tiktokOauthClientId' => ['nullable', 'string', 'max:500'],
            'tiktokOauthClientSecret' => ['nullable', 'string', 'max:500'],
            'tiktokOauthRedirect' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function dependencyErrors(SettingsGroup $group, array $values): array
    {
        if ($group === SettingsGroup::Operations) {
            $errors = [];

            if ((int) $values['opsSessionTtlMinutes'] > (int) $values['opsSessionMaxLifetimeMinutes']) {
                $errors['opsSessionTtlMinutes'] = 'The session TTL cannot exceed the maximum lifetime.';
            }

            $overrides = $values['saleCutoffOverrides'] ?? [];
            $activeLineUuids = Line::query()
                ->whereIn('uuid', array_keys($overrides))
                ->where('status', LineStatus::Active->value)
                ->pluck('uuid')
                ->map(static fn (mixed $uuid): string => (string) $uuid)
                ->all();

            foreach ($overrides as $lineUuid => $minutes) {
                if (! is_string($lineUuid) || ! in_array($lineUuid, $activeLineUuids, true)) {
                    $errors['saleCutoffOverrides'] = 'Chaque override doit cibler une ligne active.';

                    break;
                }

                if (! is_numeric($minutes) || (int) $minutes != (float) $minutes || (int) $minutes < 0 || (int) $minutes > 1440) {
                    $errors['saleCutoffOverrides'] = 'Chaque override doit être compris entre 0 et 1440 minutes.';

                    break;
                }
            }

            return $errors;
        }

        if ($group !== SettingsGroup::Integrations) {
            return [];
        }

        $errors = [];

        if ((bool) $values['fedapayEnabled'] && blank($values['fedapaySecretKey'])) {
            $errors['fedapaySecretKey'] = 'A FedaPay secret key is required when the provider is enabled.';
        }

        if ((bool) $values['mtnMomoEnabled'] && (blank($values['mtnMomoSubscriptionKey']) || blank($values['mtnMomoUserId']) || blank($values['mtnMomoApiKey']))) {
            $errors['mtnMomoSubscriptionKey'] = 'MTN MoMo credentials are required when the provider is enabled.';
        }

        if ($values['mapProvider'] === 'google' && blank($values['mapGoogleApiKey'])) {
            $errors['mapGoogleApiKey'] = 'A Google Maps API key is required when Google is the active provider.';
        }

        foreach (['emecfTestInvoiceUrl', 'emecfTestInfoUrl', 'emecfProductionInvoiceUrl', 'emecfProductionInfoUrl'] as $field) {
            if (! $this->hasAllowedHost($values[$field], 'impots.bj')) {
                $errors[$field] = 'Les endpoints e-MECeF doivent rester sur le domaine officiel de la DGI.';
            }
        }

        if (! $this->hasAllowedHost($values['genukaUrl'], 'genuka.com')) {
            $errors['genukaUrl'] = 'L’endpoint Genuka doit rester sur le domaine officiel Genuka.';
        }

        $configuredWahaHost = strtolower((string) parse_url((string) config('services.waha.url'), PHP_URL_HOST));

        if (! $this->hasExactHost($values['wahaUrl'], 'waha')
            && ! $this->hasExactHost($values['wahaUrl'], 'localhost')
            && ! $this->hasExactHost($values['wahaUrl'], '127.0.0.1')
            && ! $this->hasExactHost($values['wahaUrl'], $configuredWahaHost)) {
            $errors['wahaUrl'] = 'L’endpoint WAHA doit rester sur le service interne autorisé.';
        }

        foreach (['google', 'facebook', 'tiktok'] as $provider) {
            $clientId = $provider.'OauthClientId';
            $clientSecret = $provider.'OauthClientSecret';

            if (filled($values[$clientId]) && blank($values[$clientSecret])) {
                $errors[$clientSecret] = 'An OAuth client secret is required when a client ID is configured.';
            }
        }

        return $errors;
    }

    private function authorize(string $permission, User $actor): void
    {
        if (enum_value($actor->status) !== UserStatus::Active->value || ! Gate::forUser($actor)->allows($permission)) {
            throw new AuthorizationException("Missing permission [{$permission}].");
        }
    }

    private function clearCache(SettingsGroup $group): void
    {
        $class = $group->settingsClass();

        app(SettingsCacheFactory::class)->build($class::repository())->clear();
    }

    private function hasAllowedHost(mixed $url, string $suffix): bool
    {
        if (! is_string($url)) {
            return false;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host !== ''
            && ! isset($parts['user'], $parts['pass'])
            && ($host === $suffix || str_ends_with($host, '.'.$suffix));
    }

    private function hasExactHost(mixed $url, string $expectedHost): bool
    {
        if ($expectedHost === '') {
            return false;
        }

        $parts = is_string($url) ? parse_url($url) : false;

        return is_array($parts)
            && ! isset($parts['user'], $parts['pass'])
            && strtolower((string) ($parts['host'] ?? '')) === $expectedHost;
    }

    private function settings(SettingsGroup $group): Settings
    {
        return app($group->settingsClass());
    }

    private function lockGroup(SettingsGroup $group): void
    {
        $this->database->table(config('settings.repositories.database.table') ?? 'settings')
            ->where('group', $group->value)
            ->lockForUpdate()
            ->get(['name']);
    }

    /** @return list<string> */
    private function sensitiveProperties(Settings $settings): array
    {
        return array_values($settings::encrypted());
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  list<string>  $sensitive
     */
    private function recordAudit(
        SettingsGroup $group,
        User $actor,
        array $before,
        array $after,
        array $sensitive,
    ): void {
        $changed = [];
        $auditBefore = [];
        $auditAfter = [];

        foreach ($after as $name => $value) {
            if (($before[$name] ?? null) === $value) {
                continue;
            }

            $changed[] = $name;

            if (in_array($name, $sensitive, true)) {
                $auditBefore[$name] = filled($before[$name] ?? null);
                $auditAfter[$name] = filled($value);

                continue;
            }

            $auditBefore[$name] = $before[$name] ?? null;
            $auditAfter[$name] = $value;
        }

        if ($changed === []) {
            return;
        }

        activity('settings')
            ->causedBy($actor)
            ->event('settings.updated')
            ->withProperties([
                'group' => $group->value,
                'fields' => $changed,
                'before' => $auditBefore,
                'after' => $auditAfter,
            ])
            ->log('settings.updated');
    }
}
