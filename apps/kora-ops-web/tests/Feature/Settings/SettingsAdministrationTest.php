<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\Settings\GeneralSettings;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Settings\Settings\OperationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function settingsViewer(string $permission): User
{
    $user = User::factory()->create();
    $user->givePermissionTo($permission);

    return $user;
}

function settingsAdministrator(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

it('renders a typed general snapshot for a viewer without exposing infrastructure values', function (): void {
    $viewer = settingsViewer('settings.general.view');

    $this->actingAs($viewer)
        ->get(route('administration.settings.general'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/settings')
            ->where('group', 'general')
            ->where('permissions.canView', true)
            ->where('permissions.canManage', false)
            ->where('snapshot.values.timezone', 'Africa/Porto-Novo')
            ->missing('snapshot.values.appKey')
            ->missing('snapshot.values.databasePassword'),
        );
});

it('refuses direct access and mutations without the group permission', function (): void {
    $viewer = settingsViewer('settings.general.view');

    $this->actingAs($viewer)
        ->patch(route('administration.settings.general.update'), [
            'values' => ['displayName' => 'Accès refusé'],
        ])
        ->assertForbidden();

    $this->actingAs(User::factory()->create())
        ->get(route('administration.settings.operations'))
        ->assertForbidden();
});

it('refuses settings access for suspended users even when permissions remain assigned', function (): void {
    $suspended = settingsAdministrator();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();

    $this->actingAs($suspended)
        ->get(route('administration.settings.general'))
        ->assertForbidden();

    $this->actingAs($suspended)
        ->patch(route('administration.settings.general.update'), [
            'values' => ['displayName' => 'Ne doit pas être appliqué'],
        ])
        ->assertForbidden();
});

it('persists a group update through the settings coordinator', function (): void {
    $admin = settingsAdministrator();

    $this->actingAs($admin)
        ->patch(route('administration.settings.general.update'), [
            'values' => [
                'displayName' => 'KORA OPS Administration',
                'currency' => 'XOF',
            ],
        ])
        ->assertRedirect(route('administration.settings.general', absolute: false));

    expect(app(GeneralSettings::class)->displayName)
        ->toBe('KORA OPS Administration');
});

it('requires explicit confirmation for impactful operations changes', function (): void {
    $admin = settingsAdministrator();

    $this->actingAs($admin)
        ->patch(route('administration.settings.operations.update'), [
            'values' => ['saleCutoffMinutes' => 20],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('confirmed');

    $this->actingAs($admin)
        ->patch(route('administration.settings.operations.update'), [
            'values' => ['saleCutoffMinutes' => 20],
            'confirmed' => true,
        ])
        ->assertRedirect(route('administration.settings.operations', absolute: false));

    expect(app(OperationsSettings::class)->saleCutoffMinutes)
        ->toBe(20);
});

it('does not ask for an impact confirmation when only alert delivery changes', function (): void {
    $admin = settingsAdministrator();

    $this->actingAs($admin)
        ->patch(route('administration.settings.operations.update'), [
            'values' => ['notificationsMailEnabled' => false],
        ])
        ->assertRedirect(route('administration.settings.operations', absolute: false));
});

it('keeps secrets masked through the integrations page and preserves blank submissions', function (): void {
    $admin = settingsAdministrator();
    $coordinator = app(SettingsCoordinator::class);

    $this->actingAs($admin);
    $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfToken' => 'route-secret']),
        $admin,
    );

    $this->get(route('administration.settings.integrations'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/settings')
            ->where('snapshot.values.emecfToken', true)
            ->missing('snapshot.values.emecfToken.value'),
        );

    $this->patch(route('administration.settings.integrations.update'), [
        'values' => ['emecfToken' => ''],
    ])->assertRedirect();

    expect(app(IntegrationsSettings::class)->emecfToken)->toBe('route-secret');
});

it('sanitizes map attribution before it is persisted for browser map views', function (): void {
    $admin = settingsAdministrator();

    $this->actingAs($admin)
        ->patch(route('administration.settings.integrations.update'), [
            'values' => [
                'mapOsmAttribution' => '<img src=x onerror=alert(1)> OpenStreetMap',
            ],
        ])
        ->assertRedirect();

    expect(app(IntegrationsSettings::class)->mapOsmAttribution)
        ->toBe('OpenStreetMap')
        ->not->toContain('<');
});

it('rejects provider endpoints outside their trusted integration domains', function (): void {
    $admin = settingsAdministrator();

    $this->actingAs($admin)
        ->patch(route('administration.settings.integrations.update'), [
            'values' => [
                'emecfTestInfoUrl' => 'https://attacker.example/collect',
                'genukaUrl' => 'https://attacker.example',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['emecfTestInfoUrl', 'genukaUrl']);
});

it('tests e-MECeF without creating a fiscal document and audits only safe fields', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://developper.impots.bj/sygmef-emcf/api/info/status' => Http::response([
            'status' => true,
            'version' => '1.0',
            'ifu' => '3202673225149',
            'nim' => 'TS01019552',
            'tokenValid' => '2027-02-27T14:12:01+01:00',
            'serverDateTime' => '2026-09-23T09:45:13+01:00',
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

    $admin = settingsAdministrator();
    $coordinator = app(SettingsCoordinator::class);

    $this->actingAs($admin);
    $coordinator->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate(['emecfToken' => 'probe-secret']),
        $admin,
    );

    $this->post(route('administration.settings.integrations.test', ['provider' => 'emecf']))
        ->assertRedirect();

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/info/status'));

    $activity = Activity::query()
        ->where('event', 'settings.connection_tested')
        ->latest('id')
        ->firstOrFail();

    expect($activity->properties['provider'])->toBe('emecf')
        ->and($activity->properties['status'])->toBe('success')
        ->and($activity->properties['safeMessage'])->toContain('3202673225149')
        ->and($activity->properties['safeMessage'])->toContain('KORA EXPRESS')
        ->and((string) $activity->properties)->not->toContain('probe-secret');
});
