<?php

use App\Models\User;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\Services\IntegrationConnectionProbeManager;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function conexteoAdministrator(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

function storeConexteoConfiguration(User $admin): void
{
    app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'conexteoEnabled' => true,
            'conexteoAppId' => 'app-123',
            'conexteoApiKey' => 'secret-api-key',
            'conexteoSender' => 'KoraExpress',
        ]),
        $admin,
    );
}

it('stores the Conexteo configuration and masks the api key', function (): void {
    $admin = conexteoAdministrator();
    $this->actingAs($admin);

    $snapshot = app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'conexteoEnabled' => true,
            'conexteoAppId' => 'app-123',
            'conexteoApiKey' => 'secret-api-key',
            'conexteoSender' => 'KoraExpress',
        ]),
        $admin,
    );

    expect($snapshot->values['conexteoEnabled'])->toBeTrue()
        ->and($snapshot->values['conexteoAppId'])->toBe('app-123')
        ->and($snapshot->values['conexteoSender'])->toBe('KoraExpress')
        ->and($snapshot->values['conexteoApiKey'])->toBeTrue()
        ->and($snapshot->sensitiveProperties)->toContain('conexteoApiKey');

    $stored = (string) DB::table('settings')->where('name', 'conexteoApiKey')->value('payload');
    expect($stored)->not->toContain('secret-api-key');
});

it('rejects enabling Conexteo without complete credentials', function (): void {
    $admin = conexteoAdministrator();
    $this->actingAs($admin);

    expect(fn () => app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'conexteoEnabled' => true,
            'conexteoAppId' => null,
            'conexteoApiKey' => null,
            'conexteoSender' => 'KoraExpress',
        ]),
        $admin,
    ))->toThrow(ValidationException::class);
});

it('rejects a sender longer than eleven characters', function (): void {
    $admin = conexteoAdministrator();
    $this->actingAs($admin);

    expect(fn () => app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['conexteoSender' => 'KoraExpressPlus']),
        $admin,
    ))->toThrow(ValidationException::class);
});

it('probes Conexteo credits without sending an SMS', function (): void {
    storeConexteoConfiguration(conexteoAdministrator());
    Http::preventStrayRequests();
    Http::fake([
        'api.conexteo.com/*' => Http::response([
            'success' => true,
            'credits_used' => 1,
            'credits_remaining' => 99,
        ]),
    ]);

    $result = app(IntegrationConnectionProbeManager::class)->test('conexteo');

    expect($result->status)->toBe('success')
        ->and($result->safeMessage)->toContain('1 crédit(s) requis')
        ->and($result->safeMessage)->toContain('99 crédit(s) restant(s)')
        ->and($result->safeMessage)->not->toContain('secret-api-key');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'resumeBefore=true'));
});

it('reports refused Conexteo credentials and unavailable timeouts', function (): void {
    storeConexteoConfiguration(conexteoAdministrator());
    Http::fake(['api.conexteo.com/*' => Http::response(['error' => 'unauthorized'], 401)]);

    $refused = app(IntegrationConnectionProbeManager::class)->test('conexteo');
    expect($refused->status)->toBe('failed')
        ->and($refused->safeMessage)->toContain('401');

    Http::fake(['api.conexteo.com/*' => fn () => throw new ConnectionException('timeout')]);

    $timeout = app(IntegrationConnectionProbeManager::class)->test('conexteo');
    expect($timeout->status)->toBe('unavailable')
        ->and($timeout->safeMessage)->toContain('indisponible');
});

it('sends a test SMS and journals it', function (): void {
    $admin = conexteoAdministrator();
    storeConexteoConfiguration($admin);
    Http::preventStrayRequests();
    Http::fake([
        'api.conexteo.com/*' => Http::response(['success' => true, 'message_id' => 'test-1', 'credits_used' => 1]),
    ]);

    $this->actingAs($admin)
        ->post(route('administration.settings.integrations.test-sms'), ['phone' => '+22997000000'])
        ->assertRedirect();

    $log = NotificationLog::query()->where('channel', 'sms')->where('template', 'test_sms')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->recipient)->toBe('+22997000000')
        ->and($log->reference_id)->toBeNull();
});

it('requires the integrations permission to send a test SMS', function (): void {
    storeConexteoConfiguration(conexteoAdministrator());

    $this->actingAs(User::factory()->create())
        ->post(route('administration.settings.integrations.test-sms'), ['phone' => '+22997000000'])
        ->assertForbidden();
});

it('validates the test SMS phone in international format', function (): void {
    $admin = conexteoAdministrator();

    $this->actingAs($admin)
        ->postJson(route('administration.settings.integrations.test-sms'), ['phone' => '97000000'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('does not call Conexteo when it is not configured', function (): void {
    $admin = conexteoAdministrator();
    Http::preventStrayRequests();
    Http::fake();

    $this->actingAs($admin)
        ->post(route('administration.settings.integrations.test-sms'), ['phone' => '+22997000000'])
        ->assertRedirect();

    Http::assertNothingSent();
    expect(NotificationLog::query()->where('channel', 'sms')->count())->toBe(0);
});

it('keeps the api key out of the settings page props', function (): void {
    $admin = conexteoAdministrator();
    storeConexteoConfiguration($admin);

    $this->actingAs($admin)
        ->get(route('administration.settings.integrations'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('snapshot.values.conexteoApiKey', true)
            ->where('snapshot.values.conexteoAppId', 'app-123'));
});
