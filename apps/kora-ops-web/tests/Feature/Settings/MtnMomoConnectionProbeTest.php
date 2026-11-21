<?php

use App\Modules\Settings\Services\IntegrationConnectionProbeManager;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function mtnProbeSettings(array $overrides = []): void
{
    $values = array_merge([
        'mtnMomoEnabled' => true,
        'mtnMomoSubscriptionKey' => 'subscription-key',
        'mtnMomoUserId' => 'api-user-id',
        'mtnMomoApiKey' => 'api-key',
        'mtnMomoEnvironment' => 'sandbox',
        'mtnMomoCallbackUrl' => 'https://staging.kora.test/api/v1/payments/webhook/mtn-momo',
        'mtnMomoTargetEnvironment' => 'mtnbenin',
        'mtnMomoSandboxCurrency' => 'EUR',
        'mtnMomoProductionCurrency' => 'XOF',
        'mtnMomoWebhookSecret' => str_repeat('p', 32),
    ], $overrides);

    $settings = app(IntegrationsSettings::class);

    foreach ($values as $key => $value) {
        $settings->{$key} = $value;
    }

    $settings->save();
    $settings->refresh();
}

it('rejects an incomplete MTN configuration before any HTTP call', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    mtnProbeSettings(['mtnMomoApiKey' => null]);

    $result = app(IntegrationConnectionProbeManager::class)->test('mtn_momo');

    expect($result->status)->toBe('failed')
        ->and($result->safeMessage)->toContain('identifiants MTN MoMo sont incomplets');

    Http::assertNothingSent();
});

it('rejects a missing callback secret and an unknown target environment', function (string $override, string $message): void {
    Http::preventStrayRequests();
    Http::fake();

    mtnProbeSettings($override === 'callback'
        ? ['mtnMomoWebhookSecret' => null]
        : ['mtnMomoTargetEnvironment' => 'mtnunknown']);

    $result = app(IntegrationConnectionProbeManager::class)->test('mtn_momo');

    expect($result->status)->toBe('failed')
        ->and($result->safeMessage)->toContain($message);

    Http::assertNothingSent();
})->with([
    'callback' => ['callback', 'callback MTN MoMo est incomplète'],
    'target' => ['target', 'environnement cible MTN MoMo n’est pas reconnu'],
]);

it('tests the token endpoint with the configured base url and target environment', function (): void {
    config()->set('payments.providers.mtn_momo.base_url', 'https://momoapi.momo.africa');

    Http::preventStrayRequests();
    Http::fake([
        'https://momoapi.momo.africa/collection/token/' => Http::response(['access_token' => 'token-1']),
    ]);

    mtnProbeSettings();

    $result = app(IntegrationConnectionProbeManager::class)->test('mtn_momo');

    expect($result->status)->toBe('success')
        ->and($result->safeMessage)->toContain('MTN MoMo répond correctement')
        ->and($result->safeMessage)->not->toContain('api-key');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://momoapi.momo.africa/collection/token/'
        && $request->hasHeader('X-Target-Environment', 'mtnbenin')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'subscription-key'));
});

it('reports a refused connection without leaking credentials', function (): void {
    Http::preventStrayRequests();
    Http::fake(fn () => Http::response([], 401));

    mtnProbeSettings();

    $result = app(IntegrationConnectionProbeManager::class)->test('mtn_momo');

    expect($result->status)->toBe('failed')
        ->and($result->safeMessage)->toContain('refusé la vérification')
        ->and($result->safeMessage)->not->toContain('api-key');
});

it('reports an unavailable provider on connection failure', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        '*' => fn () => throw new ConnectionException('timeout'),
    ]);

    mtnProbeSettings();

    $result = app(IntegrationConnectionProbeManager::class)->test('mtn_momo');

    expect($result->status)->toBe('unavailable')
        ->and($result->safeMessage)->toContain('indisponible');
});
