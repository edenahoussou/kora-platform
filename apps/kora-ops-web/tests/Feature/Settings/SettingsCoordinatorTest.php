<?php

use App\Models\User;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\GeneralSettingsUpdate;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\DTO\OperationsSettingsUpdate;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function settingsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

it('loads deterministic defaults and applies a general update through the seam', function (): void {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $coordinator = app(SettingsCoordinator::class);

    $snapshot = $coordinator->snapshot(SettingsGroup::General);

    expect($snapshot->group)->toBe(SettingsGroup::General)
        ->and($snapshot->values['timezone'])->toBe('Africa/Porto-Novo')
        ->and($snapshot->values['currency'])->toBe('XOF');

    $updated = $coordinator->update(
        SettingsGroup::General,
        new GeneralSettingsUpdate([
            'displayName' => 'KORA BUS Bénin',
            'locale' => 'fr',
            'fallbackLocale' => 'en',
            'timezone' => 'Africa/Porto-Novo',
            'currency' => 'XOF',
        ]),
        $admin,
    );

    expect($updated->values['displayName'])->toBe('KORA BUS Bénin')
        ->and($coordinator->snapshot(SettingsGroup::General)->values['displayName'])->toBe('KORA BUS Bénin');
});

it('redacts, encrypts, preserves and explicitly clears integration secrets', function (): void {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $coordinator = app(SettingsCoordinator::class);

    $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'emecfToken' => 'first-secret',
            'genukaConnectionId' => 'genuka-connection-secret',
        ]),
        $admin,
    );

    $snapshot = $coordinator->snapshot(SettingsGroup::Integrations);
    expect($snapshot->values['emecfToken'])->toBeTrue()
        ->and($snapshot->values['genukaConnectionId'])->toBeTrue()
        ->and($snapshot->sensitiveProperties)->toContain('emecfToken');

    $payload = (string) DB::table('settings')
        ->where('group', 'integrations')
        ->where('name', 'emecfToken')
        ->value('payload');

    expect($payload)->not->toContain('first-secret');

    $connectionPayload = (string) DB::table('settings')
        ->where('group', 'integrations')
        ->where('name', 'genukaConnectionId')
        ->value('payload');

    expect($connectionPayload)->not->toContain('genuka-connection-secret');

    $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfToken' => '']),
        $admin,
    );

    expect(app(IntegrationsSettings::class)->emecfToken)->toBe('first-secret');

    $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([], ['emecfToken']),
        $admin,
    );

    expect($coordinator->snapshot(SettingsGroup::Integrations)->values['emecfToken'])->toBeFalse();
    expect(DB::table('activity_log')->latest('id')->value('properties'))->not->toContain('first-secret');
});

it('stores Firebase push configuration in integrations and redacts the service account key', function (): void {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $coordinator = app(SettingsCoordinator::class);
    $privateKey = "-----BEGIN PRIVATE KEY-----\nunit-test-private-key\n-----END PRIVATE KEY-----\n";
    $serviceAccount = json_encode([
        'type' => 'service_account',
        'project_id' => 'kora-test',
        'client_email' => 'firebase-adminsdk@kora-test.iam.gserviceaccount.com',
        'private_key' => $privateKey,
    ], JSON_THROW_ON_ERROR);

    $snapshot = $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'firebasePushEnabled' => true,
            'firebaseProjectId' => 'kora-test',
            'firebaseWebApiKey' => 'public-web-api-key',
            'firebaseWebMessagingSenderId' => '123456789',
            'firebaseWebAppId' => '1:123456789:web:abcd',
            'firebaseWebVapidKey' => 'public-vapid-key',
            'firebaseServiceAccountJson' => $serviceAccount,
        ]),
        $admin,
    );

    expect($snapshot->values['firebaseServiceAccountJson'])->toBeTrue()
        ->and($snapshot->sensitiveProperties)->toContain('firebaseServiceAccountJson');

    $payload = (string) DB::table('settings')
        ->where('group', 'integrations')
        ->where('name', 'firebaseServiceAccountJson')
        ->value('payload');

    expect($payload)->not->toContain('unit-test-private-key')
        ->and($payload)->not->toContain($privateKey);
});

it('rejects enabling Firebase push when the service account project does not match', function (): void {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $coordinator = app(SettingsCoordinator::class);
    $serviceAccount = json_encode([
        'type' => 'service_account',
        'project_id' => 'another-project',
        'client_email' => 'firebase-adminsdk@another-project.iam.gserviceaccount.com',
        'private_key' => 'private-key',
    ], JSON_THROW_ON_ERROR);

    expect(fn () => $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'firebasePushEnabled' => true,
            'firebaseProjectId' => 'kora-test',
            'firebaseWebApiKey' => 'public-web-api-key',
            'firebaseWebMessagingSenderId' => '123456789',
            'firebaseWebAppId' => '1:123456789:web:abcd',
            'firebaseWebVapidKey' => 'public-vapid-key',
            'firebaseServiceAccountJson' => $serviceAccount,
        ]),
        $admin,
    ))->toThrow(ValidationException::class);
});

it('rejects unauthorized updates, invalid values and no-op audit writes', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('settings.general.view');
    $this->actingAs($viewer);
    $coordinator = app(SettingsCoordinator::class);

    expect(fn () => $coordinator->update(
        SettingsGroup::General,
        new GeneralSettingsUpdate(['displayName' => 'Interdit']),
        $viewer,
    ))->toThrow(AuthorizationException::class);

    $admin = settingsAdmin();
    $this->actingAs($admin);
    $before = DB::table('activity_log')->count();

    expect(fn () => $coordinator->update(
        SettingsGroup::General,
        new GeneralSettingsUpdate(['currency' => 'EURO']),
        $admin,
    ))->toThrow(ValidationException::class);

    $coordinator->update(
        SettingsGroup::General,
        new GeneralSettingsUpdate([]),
        $admin,
    );

    expect(DB::table('activity_log')->count())->toBe($before);
});

it('enforces cross-setting dependencies before persisting integrations and operations', function (): void {
    $admin = settingsAdmin();
    $this->actingAs($admin);
    $coordinator = app(SettingsCoordinator::class);

    expect(fn () => $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['mtnMomoEnabled' => true]),
        $admin,
    ))->toThrow(ValidationException::class);

    expect(fn () => $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['mapProvider' => 'google']),
        $admin,
    ))->toThrow(ValidationException::class);

    expect(fn () => $coordinator->update(
        SettingsGroup::Operations,
        new OperationsSettingsUpdate(['opsSessionTtlMinutes' => 2000]),
        $admin,
    ))->toThrow(ValidationException::class);
});
