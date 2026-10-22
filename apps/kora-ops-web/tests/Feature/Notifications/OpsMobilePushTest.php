<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\IdentityAccess\Models\UserPreference;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Jobs\SendOpsPushJob;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function opsPushUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function opsPushDevice(User $user, ?string $token = null): UserDevice
{
    return UserDevice::query()->create([
        'user_id' => $user->id,
        'name' => 'P501',
        'platform' => DevicePlatform::Android,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => $token,
        'status' => UserDeviceStatus::Active,
    ]);
}

function opsPushFirebaseConfigured(): FirebaseMessagingClient
{
    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->andReturn(true);
    app()->instance(FirebaseMessagingClient::class, $firebase);

    return $firebase;
}

it('registers the FCM token of a convoyeur device without any stored preference', function (): void {
    opsPushFirebaseConfigured();
    $conductor = opsPushUser(SystemRole::Conductor->value);
    $device = opsPushDevice($conductor);

    $this->actingAs($conductor)
        ->putJson("/api/v1/auth/devices/{$device->uuid}/push-token", ['enabled' => true, 'push_token' => 'ops-fcm-token'])
        ->assertOk()
        ->assertJsonPath('data.enabled', true);

    expect($device->refresh()->push_token)->toBe('ops-fcm-token');
});

it('still requires an explicit push opt-in from a passenger', function (): void {
    opsPushFirebaseConfigured();
    $passenger = opsPushUser(SystemRole::Passenger->value);
    $device = opsPushDevice($passenger);

    $this->actingAs($passenger)
        ->putJson("/api/v1/auth/devices/{$device->uuid}/push-token", ['enabled' => true, 'push_token' => 'passenger-token'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('enabled');

    expect($device->refresh()->push_token)->toBeNull();
});

it('refuses the token of a convoyeur who turned push off', function (): void {
    opsPushFirebaseConfigured();
    $conductor = opsPushUser(SystemRole::Conductor->value);
    UserPreference::query()->create(['user_id' => $conductor->id, 'push_notifications' => false]);
    $device = opsPushDevice($conductor);

    $this->actingAs($conductor)
        ->putJson("/api/v1/auth/devices/{$device->uuid}/push-token", ['enabled' => true, 'push_token' => 'ops-fcm-token'])
        ->assertUnprocessable();
});

it('creates ops preferences with push enabled by default', function (): void {
    $conductor = opsPushUser(SystemRole::Conductor->value);

    $this->actingAs($conductor)
        ->getJson('/api/v1/auth/preferences')
        ->assertOk()
        ->assertJsonPath('data.push_notifications', true);
});

it('sends the action and departure uuid needed by the mobile deep link', function (): void {
    $conductor = opsPushUser(SystemRole::Conductor->value);
    $device = opsPushDevice($conductor, 'ops-fcm-token');
    $line = Line::query()->create([
        'code' => 'PU-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Lomé',
        'type' => 'internationale', 'countries_crossed' => ['BJ', 'TG'],
        'distance_km' => 150, 'estimated_duration_minutes' => 180, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'PU-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $departure = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-22', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHours(3), 'status' => 'scheduled',
        'conductor_id' => $conductor->id,
    ]);
    $event = OpsNotificationEvent::query()->create([
        'dedupe_key' => 'departure_assigned:'.Str::uuid(),
        'type' => OpsNotificationType::DepartureAssigned,
        'severity' => OpsNotificationSeverity::Info,
        'title' => 'Départ affecté',
        'message' => 'Vous êtes affecté au départ.',
        'data' => [],
        'departure_id' => $departure->id,
        'occurred_at' => now(),
    ]);
    $notificationUuid = Str::uuid()->toString();

    $fcm = Mockery::mock(FirebaseMessagingClient::class);
    $fcm->shouldReceive('isServerConfigured')->once()->andReturn(true);
    $fcm->shouldReceive('send')
        ->once()
        ->with('ops-fcm-token', Mockery::on(fn (array $message): bool => $message['platform'] === 'android'
            && $message['data']['type'] === 'departure_assigned'
            && $message['data']['action'] === 'open_departure'
            && $message['data']['severity'] === 'info'
            && $message['data']['departure_uuid'] === $departure->uuid
            && $message['data']['notification_uuid'] === $notificationUuid))
        ->andReturn(true);

    (new SendOpsPushJob($event->id, $device->id, 'device:'.$device->uuid, $notificationUuid))->handle($fcm);
});
