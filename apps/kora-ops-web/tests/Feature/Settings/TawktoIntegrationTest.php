<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Settings\Contracts\SettingsCoordinator;
use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\DTO\IntegrationsSettingsUpdate;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('stores the tawk.to configuration in integrations settings', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $snapshot = app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'tawktoEnabled' => true,
            'tawktoPropertyId' => '64f0a1b2c3d4e5f6',
            'tawktoWidgetId' => 'default',
        ]),
        $admin,
    );

    expect($snapshot->values['tawktoEnabled'])->toBeTrue()
        ->and($snapshot->values['tawktoPropertyId'])->toBe('64f0a1b2c3d4e5f6')
        ->and($snapshot->values['tawktoWidgetId'])->toBe('default');
});

it('rejects enabling tawk.to without a property id', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    expect(fn () => app(SettingsCoordinator::class)->update(
        SettingsGroup::Integrations,
        new IntegrationsSettingsUpdate([
            'tawktoEnabled' => true,
            'tawktoPropertyId' => null,
            'tawktoWidgetId' => 'default',
        ]),
        $admin,
    ))->toThrow(ValidationException::class);
});

it('requires authentication for the passenger support chat configuration', function (): void {
    $this->getJson('/api/v1/auth/passenger/support-chat-configuration')->assertUnauthorized();
});

it('reports the chat as disabled until tawk.to is configured', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/auth/passenger/support-chat-configuration')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonMissingPath('data.property_id');
});

it('exposes the connected agent identity to the tawk.to dashboard', function (): void {
    $settings = app(IntegrationsSettings::class);
    $settings->tawktoEnabled = true;
    $settings->tawktoPropertyId = '64f0a1b2c3d4e5f6';
    $settings->tawktoWidgetId = 'default';
    $settings->tawktoApiKey = 'test-api-key';
    $settings->save();

    $agency = Agency::factory()->create(['name' => 'Agence Étoile']);
    $agent = User::factory()->create([
        'name' => 'Agent Guichet',
        'email' => 'agent@kora.test',
        'phone' => '+22997000000',
        'job_title' => 'Chef de guichet',
        'agency_id' => $agency->id,
    ]);
    $agent->assignRole('system_admin');

    $this->actingAs($agent)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('supportChat.visitor.name', 'Agent Guichet')
            ->where('supportChat.visitor.email', 'agent@kora.test')
            ->where('supportChat.visitor.phone', '+22997000000')
            ->where('supportChat.visitor.jobTitle', 'Chef de guichet')
            ->where('supportChat.visitor.agency', 'Agence Étoile')
            ->where('supportChat.visitor.role', 'Administrateur système')
            ->where('supportChat.visitorHash', hash_hmac('sha256', 'agent@kora.test', 'test-api-key')));
});

it('exposes the public tawk.to configuration to the passenger app', function (): void {
    $settings = app(IntegrationsSettings::class);
    $settings->tawktoEnabled = true;
    $settings->tawktoPropertyId = '64f0a1b2c3d4e5f6';
    $settings->tawktoWidgetId = 'default';
    $settings->tawktoApiKey = 'test-api-key';
    $settings->save();

    $user = User::factory()->create([
        'name' => 'Awa Kora',
        'email' => 'passager@kora.test',
        'phone' => '+22997000000',
    ]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/passenger/support-chat-configuration')
        ->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.property_id', '64f0a1b2c3d4e5f6')
        ->assertJsonPath('data.widget_id', 'default')
        ->assertJsonPath('data.direct_chat_url', 'https://tawk.to/chat/64f0a1b2c3d4e5f6/default')
        ->assertJsonPath('data.secure_hash', hash_hmac('sha256', 'passager@kora.test', 'test-api-key'))
        ->assertJsonPath('data.visitor.name', 'Awa Kora')
        ->assertJsonPath('data.visitor.email', 'passager@kora.test')
        ->assertJsonPath('data.visitor.phone', '+22997000000');
});
