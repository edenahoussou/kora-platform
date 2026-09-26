<?php

use App\Models\User;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\Services\IntegrationConnectionProbeManager;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function probeAdministrator(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

function storeEmecfToken(User $admin, string $token = 'probe-secret'): void
{
    app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfToken' => $token]),
        $admin,
    );
}

it('hits the e-MECeF information status endpoint and reports account details', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/sygmef-emcf/api/info/status' => Http::response([
            'status' => true,
            'version' => '1.0',
            'ifu' => '3202673225149',
            'nim' => 'TS01019552',
            'tokenValid' => '2027-02-27T14:12:01+01:00',
            'emcfList' => [[
                'nim' => 'TS01019552',
                'status' => 'Actif',
                'shopName' => 'KORA EXPRESS',
                'address3' => 'COTONOU',
                'contact1' => '0190109648',
            ]],
        ]),
    ]);

    storeEmecfToken(probeAdministrator());

    $result = app(IntegrationConnectionProbeManager::class)->test('emecf');

    expect($result->status)->toBe('success')
        ->and($result->safeMessage)->toContain('IFU 3202673225149')
        ->and($result->safeMessage)->toContain('KORA EXPRESS')
        ->and($result->safeMessage)->toContain('27/02/2027')
        ->and($result->safeMessage)->not->toContain('probe-secret');

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/info/status')
        && $request->hasHeader('Authorization', 'Bearer probe-secret'));
});

it('stores the taxpayer account details returned by the DGI on first sync', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/sygmef-emcf/api/info/status' => Http::response([
            'status' => true,
            'version' => '1.0',
            'ifu' => '3202673225149',
            'nim' => 'TS01019552',
            'tokenValid' => '2027-02-27T14:12:01+01:00',
            'emcfList' => [[
                'nim' => 'TS01019552',
                'status' => 'Actif',
                'shopName' => 'KORA EXPRESS',
                'address1' => 'Ilot 190, Sèdjro',
                'address3' => 'COTONOU',
                'contact1' => '0190109648',
                'contact2' => 'ops@kora.test',
            ]],
        ]),
    ]);

    $admin = probeAdministrator();
    storeEmecfToken($admin);

    app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfDefaultIfu' => '0000000000000']),
        $admin,
    );

    app(IntegrationConnectionProbeManager::class)->test('emecf');

    $settings = app(IntegrationsSettings::class)->refresh();

    expect($settings->emecfNim)->toBe('TS01019552')
        ->and($settings->emecfShopName)->toBe('KORA EXPRESS')
        ->and($settings->emecfAddress1)->toBe('Ilot 190, Sèdjro')
        ->and($settings->emecfContact1)->toBe('0190109648')
        ->and($settings->emecfApiVersion)->toBe('1.0')
        ->and($settings->emecfTokenValidUntil)->toBe('2027-02-27T14:12:01+01:00')
        ->and($settings->emecfDefaultIfu)->toBe('3202673225149');
});

it('never overwrites account details already managed by administrators', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/sygmef-emcf/api/info/status' => Http::response([
            'status' => true,
            'nim' => 'TS01019552',
            'emcfList' => [['nim' => 'TS01019552', 'shopName' => 'API SHOP']],
        ]),
    ]);

    $admin = probeAdministrator();
    storeEmecfToken($admin);

    app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfNim' => 'MANUAL-NIM', 'emecfShopName' => 'KORA MANUEL']),
        $admin,
    );

    app(IntegrationConnectionProbeManager::class)->test('emecf');

    $settings = app(IntegrationsSettings::class)->refresh();

    expect($settings->emecfNim)->toBe('MANUAL-NIM')
        ->and($settings->emecfShopName)->toBe('KORA MANUEL');
});

it('normalizes a stored info URL that still points to the API root', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/*' => Http::response(['status' => true, 'ifu' => '1', 'nim' => 'N1']),
    ]);

    $admin = probeAdministrator();
    storeEmecfToken($admin);

    app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfTestInfoUrl' => 'https://developper.impots.bj/sygmef-emcf/api/']),
        $admin,
    );

    $result = app(IntegrationConnectionProbeManager::class)->test('emecf');

    expect($result->status)->toBe('success');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://developper.impots.bj/sygmef-emcf/api/info/status');
});

it('fails when e-MECeF rejects the token', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/*' => Http::response(['message' => 'Unauthorized'], 401),
    ]);

    storeEmecfToken(probeAdministrator());

    $result = app(IntegrationConnectionProbeManager::class)->test('emecf');

    expect($result->status)->toBe('failed')
        ->and($result->safeMessage)->toContain('refusé');
});

it('reports e-MECeF as unavailable when the request cannot complete', function (): void {
    Http::fake([
        'https://developper.impots.bj/*' => fn () => throw new ConnectionException('timeout'),
    ]);

    storeEmecfToken(probeAdministrator());

    $result = app(IntegrationConnectionProbeManager::class)->test('emecf');

    expect($result->status)->toBe('unavailable')
        ->and($result->safeMessage)->toContain('indisponible');
});

it('fails fast when the e-MECeF token is not configured', function (): void {
    Http::preventStrayRequests();

    $settings = app(IntegrationsSettings::class);
    $settings->emecfToken = null;
    $settings->save();

    $result = app(IntegrationConnectionProbeManager::class)->test('emecf');

    expect($result->status)->toBe('failed')
        ->and($result->safeMessage)->toContain('pas configuré');

    Http::assertNothingSent();
});
