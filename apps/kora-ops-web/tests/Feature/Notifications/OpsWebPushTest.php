<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Actions\PublishOpsNotificationAction;
use App\Modules\Notifications\Channels\OpsFirebaseChannel;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Data\OpsNotificationSeverity;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Jobs\SendOpsPushJob;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Notifications\OpsPushNotification;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Notifications\Services\OpsNotificationRecipientResolver;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('stores and removes the browser registration token for the current user', function (): void {
    $user = User::factory()->create();
    $fingerprint = Str::uuid()->toString();

    $this->actingAs($user)
        ->postJson('/settings/notifications/push', [
            'device_fingerprint' => $fingerprint,
            'device_name' => 'Firefox desktop',
            'push_token' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    $device = UserDevice::query()->where('user_id', $user->id)->sole();
    expect($device->platform)->toBe(DevicePlatform::Web)
        ->and($device->status)->toBe(UserDeviceStatus::Active)
        ->and($device->push_token)->toBeNull();
});

it('does not accept a push registration token until server credentials are configured', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/settings/notifications/push', [
            'device_fingerprint' => Str::uuid()->toString(),
            'device_name' => 'Firefox desktop',
            'push_token' => 'untrusted-registration-token',
        ])
        ->assertUnprocessable();

    expect(UserDevice::query()->count())->toBe(0);
});

it('exposes only the web Firebase settings to an authenticated browser', function (): void {
    $settings = app(IntegrationsSettings::class);
    $settings->firebasePushEnabled = true;
    $settings->firebaseProjectId = 'kora-test';
    $settings->firebaseWebApiKey = 'public-web-api-key';
    $settings->firebaseWebAuthDomain = 'kora-test.firebaseapp.com';
    $settings->firebaseWebMessagingSenderId = '123456789';
    $settings->firebaseWebAppId = '1:123456789:web:abcd';
    $settings->firebaseWebVapidKey = 'public-vapid-key';
    $settings->firebaseServiceAccountJson = '{"private_key":"never-send-this"}';
    $settings->save();

    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->once()->andReturn(true);
    $this->app->instance(FirebaseMessagingClient::class, $firebase);

    $this->actingAs(User::factory()->create())
        ->getJson('/api/v1/notifications/web-push-configuration')
        ->assertOk()
        ->assertJsonPath('configured', true)
        ->assertJsonPath('firebase.projectId', 'kora-test')
        ->assertJsonPath('firebase.apiKey', 'public-web-api-key')
        ->assertJsonMissingPath('firebase.private_key')
        ->assertJsonMissingPath('firebaseServiceAccountJson');
});

it('sends a controlled summary for an alert and removes invalid browser tokens', function (): void {
    $recipient = User::factory()->create();
    $device = UserDevice::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Firefox desktop',
        'platform' => DevicePlatform::Web,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => 'invalid-token',
        'status' => UserDeviceStatus::Active,
    ]);
    $event = OpsNotificationEvent::query()->create([
        'dedupe_key' => 'cash-variance:'.Str::uuid(),
        'type' => OpsNotificationType::CashVarianceSubmitted,
        'severity' => OpsNotificationSeverity::Warning,
        'title' => 'Écart de caisse à approuver',
        'message' => 'Détail interne: 12 345 678 FCFA, contact +229 00000000',
        'data' => ['private_note' => 'do not send'],
        'occurred_at' => now(),
    ]);
    $settings = app(IntegrationsSettings::class);
    $settings->firebasePushEnabled = true;
    $settings->firebaseProjectId = 'test-project';
    $settings->firebaseServiceAccountJson = json_encode([
        'type' => 'service_account',
        'project_id' => 'test-project',
        'client_email' => 'firebase@test-project.iam.gserviceaccount.com',
        'private_key' => 'not-used-by-mock',
    ], JSON_THROW_ON_ERROR);
    $settings->save();

    $fcm = Mockery::mock(FirebaseMessagingClient::class);
    $fcm->shouldReceive('isServerConfigured')->once()->andReturn(true);
    $fcm->shouldReceive('send')
        ->once()
        ->with('invalid-token', Mockery::on(fn (array $message): bool => $message['title'] === 'Écart de caisse à approuver'
            && $message['body'] === 'Une clôture de caisse avec écart attend votre approbation.'
            && ! str_contains(json_encode($message), '12 345 678')
            && ! str_contains(json_encode($message), '+229')))
        ->andReturn(false);

    (new SendOpsPushJob($event->id, $device->id, 'device:'.$device->uuid))->handle($fcm);

    expect($device->refresh()->push_token)->toBeNull()
        ->and(OpsNotificationDelivery::query()
            ->where('event_id', $event->id)
            ->where('recipient_user_id', $recipient->id)
            ->where('channel', OpsNotificationChannel::Push->value)
            ->value('status'))
        ->toBe(OpsNotificationDeliveryStatus::Skipped);
});

it('limits permission-based alert recipients to the requested agency', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    $managerA = User::factory()->create(['agency_id' => $agencyA->id]);
    $managerA->assignRole(SystemRole::AgencyManager->value);
    $managerB = User::factory()->create(['agency_id' => $agencyB->id]);
    $managerB->assignRole(SystemRole::AgencyManager->value);
    $agentA = User::factory()->create(['agency_id' => $agencyA->id]);
    $agentA->assignRole(SystemRole::AgencyAgent->value);

    $recipients = app(OpsNotificationRecipientResolver::class)->usersWithPermission('cash.approve-variance', $agencyA->id);

    expect($recipients->pluck('id')->all())->toContain($managerA->id)
        ->and($recipients->pluck('id')->all())->not->toContain($managerB->id)
        ->and($recipients->pluck('id')->all())->not->toContain($agentA->id);
});

it('keeps the inbox notification type deduplication invariant for new alert types', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $recipient = User::factory()->create();
    $recipient->assignRole(SystemRole::AgencyManager->value);
    $type = OpsNotificationType::DepartureDelayed;
    $draft = new OpsNotificationDraft(
        $type,
        'departure_delayed:'.Str::uuid(),
        'Départ retardé.',
        ['delay_minutes' => 10],
        new Collection([$recipient]),
        $recipient,
        null,
        null,
        now(),
    );
    $publisher = app(PublishOpsNotificationAction::class);
    $first = $publisher->execute($draft);
    $replayed = $publisher->execute($draft);

    expect($first)->not->toBeNull()
        ->and($first->type->sendsMail())->toBeTrue()
        ->and($type->action()->value)->toBe('open_departure')
        ->and($replayed?->id)->toBe($first->id)
        ->and(OpsNotificationEvent::query()->where('dedupe_key', $draft->dedupeKey)->count())->toBe(1)
        ->and(OpsNotificationDelivery::query()->where('event_id', $first->id)->where('recipient_user_id', $recipient->id)->where('channel', OpsNotificationChannel::InApp->value)->count())->toBe(1);
});

it('attempts web push for an inbox event independently of its email classification', function (): void {
    Queue::fake();
    $this->seed(KoraBusRolePermissionSeeder::class);
    $recipient = User::factory()->create();
    $recipient->assignRole(SystemRole::AgencyManager->value);
    UserDevice::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Chrome',
        'platform' => DevicePlatform::Web,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => 'web-token',
        'status' => UserDeviceStatus::Active,
    ]);
    $draft = new OpsNotificationDraft(
        OpsNotificationType::DepartureAssigned,
        'departure-assigned:'.Str::uuid(),
        'Un départ vous a été affecté.',
        [],
        collect([$recipient]),
        $recipient,
        null,
        null,
        now(),
    );

    app(PublishOpsNotificationAction::class)->execute($draft);

    expect(OpsNotificationType::DepartureAssigned->sendsMail())->toBeFalse();
    Queue::assertPushed(SendOpsPushJob::class);
});

it('sends the alert push to android devices with a data payload and no web link', function (): void {
    $recipient = User::factory()->create();
    $device = UserDevice::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Samsung SM-S921B',
        'platform' => DevicePlatform::Android,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => 'android-token',
        'status' => UserDeviceStatus::Active,
    ]);
    $event = OpsNotificationEvent::query()->create([
        'dedupe_key' => 'cash-variance:'.Str::uuid(),
        'type' => OpsNotificationType::CashVarianceSubmitted,
        'severity' => OpsNotificationSeverity::Warning,
        'title' => 'Écart de caisse à approuver',
        'message' => 'Détail interne',
        'data' => [],
        'occurred_at' => now(),
    ]);

    $fcm = Mockery::mock(FirebaseMessagingClient::class);
    $fcm->shouldReceive('isServerConfigured')->once()->andReturn(true);
    $fcm->shouldReceive('send')
        ->once()
        ->with('android-token', Mockery::on(fn (array $message): bool => $message['platform'] === 'android'
            && ! isset($message['link'])
            && $message['data']['type'] === OpsNotificationType::CashVarianceSubmitted->value
            && $message['data']['notification_uuid'] === 'notif-uuid'))
        ->andReturn(true);

    (new SendOpsPushJob($event->id, $device->id, 'device:'.$device->uuid, 'notif-uuid'))->handle($fcm);

    expect(OpsNotificationDelivery::query()->where('event_id', $event->id)->value('status'))
        ->toBe(OpsNotificationDeliveryStatus::Sent);
});

it('dispatches the push job for android devices through the Firebase channel', function (): void {
    Queue::fake();
    $recipient = User::factory()->create();
    UserDevice::query()->create([
        'user_id' => $recipient->id,
        'name' => 'Android',
        'platform' => DevicePlatform::Android,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => 'android-token',
        'status' => UserDeviceStatus::Active,
    ]);
    $event = OpsNotificationEvent::query()->create([
        'dedupe_key' => 'cash-variance:'.Str::uuid(),
        'type' => OpsNotificationType::CashVarianceSubmitted,
        'severity' => OpsNotificationSeverity::Warning,
        'title' => 'Écart',
        'message' => 'x',
        'data' => [],
        'occurred_at' => now(),
    ]);

    app(OpsFirebaseChannel::class)
        ->send($recipient, new OpsPushNotification($event->id));

    Queue::assertPushed(SendOpsPushJob::class, 1);
});
