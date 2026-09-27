<?php

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Modules\Notifications\Jobs\SendPassengerPushJob;
use App\Modules\Notifications\Notifications\PassengerInboxNotification;
use App\Modules\Notifications\Services\FirebaseMessagingClient;
use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('routes personal reservations to the payer and company reservations to linked passenger accounts', function (): void {
    Queue::fake();
    $creator = User::factory()->create();
    $personalPayer = User::factory()->create();
    $personalTraveller = User::factory()->create();
    $companyPayer = User::factory()->create();
    $companyTraveller = User::factory()->create();
    $line = Line::query()->create([
        'code' => 'NT-'.Str::upper(Str::random(6)),
        'name' => 'Cotonou - Parakou',
        'type' => 'nationale',
        'countries_crossed' => ['BJ'],
        'distance_km' => 450,
        'estimated_duration_minutes' => 420,
        'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'NT-'.Str::upper(Str::random(8)),
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 40,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => now()->addDays(4)->toDateString(),
        'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addDays(4)->addHours(6),
        'status' => 'scheduled',
    ]);

    $personalReservation = Reservation::query()->create([
        'departure_id' => $departure->id,
        'created_by_user_id' => $creator->id,
        'payer_user_id' => $personalPayer->id,
        'payer_type' => PayerType::Personal,
        'status' => ReservationStatus::Ticketed,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);
    ReservationPassenger::query()->create([
        'reservation_id' => $personalReservation->id,
        'user_id' => $personalTraveller->id,
        'first_name' => 'Voyageur',
        'last_name' => 'Personnel',
        'passenger_index' => 0,
        'status' => 'active',
    ]);

    $companyReservation = Reservation::query()->create([
        'departure_id' => $departure->id,
        'created_by_user_id' => $creator->id,
        'payer_user_id' => $companyPayer->id,
        'payer_type' => PayerType::Company,
        'status' => ReservationStatus::Ticketed,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);
    ReservationPassenger::query()->create([
        'reservation_id' => $companyReservation->id,
        'user_id' => $companyTraveller->id,
        'first_name' => 'Voyageur',
        'last_name' => 'Entreprise',
        'passenger_index' => 0,
        'status' => 'active',
    ]);

    $publisher = app(PassengerNotificationPublisher::class);
    $publisher->reservationConfirmed($personalReservation);
    $publisher->reservationConfirmed($companyReservation);

    expect($personalPayer->notifications()->where('audience', 'passenger')->count())->toBe(1)
        ->and($personalTraveller->notifications()->where('audience', 'passenger')->count())->toBe(0)
        ->and($companyPayer->notifications()->where('audience', 'passenger')->count())->toBe(0)
        ->and($companyTraveller->notifications()->where('audience', 'passenger')->count())->toBe(1);
});

it('isolates the passenger inbox, filters unread items, and preserves read state', function (): void {
    Queue::fake();
    $passenger = User::factory()->create();
    $otherPassenger = User::factory()->create();

    Notification::sendNow($passenger, new PassengerInboxNotification('bus:ticket:one', 'reservation_confirmed', 'Réservation confirmée', 'Votre billet est prêt.', 'reservation-uuid'));
    Notification::sendNow($passenger, new PassengerInboxNotification('bus:refund:one', 'refund_completed', 'Remboursement effectué', 'Le remboursement a été effectué.', 'reservation-uuid'));
    Notification::sendNow($otherPassenger, new PassengerInboxNotification('bus:ticket:other', 'reservation_confirmed', 'Réservation confirmée', 'Votre billet est prêt.', 'other-reservation'));
    $passenger->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'ops',
        'audience' => 'ops',
        'user_id' => $passenger->getKey(),
        'data' => ['audience' => 'ops', 'type' => 'departure_assigned'],
    ]);

    $all = $this->actingAs($passenger)->getJson('/api/v1/auth/passenger/notifications')->assertOk();
    expect($all->json('data'))->toHaveCount(2)
        ->and($all->json('meta.unread_count'))->toBe(2);

    $firstUuid = $all->json('data.0.uuid');
    $this->postJson("/api/v1/auth/passenger/notifications/{$firstUuid}/read")->assertOk()->assertJsonPath('data.is_read', true);
    $unread = $this->getJson('/api/v1/auth/passenger/notifications?unread=1')->assertOk();
    expect($unread->json('data'))->toHaveCount(1)
        ->and($unread->json('data.0.type'))->toBe('refund_completed');

    $otherUuid = $otherPassenger->notifications()->where('audience', 'passenger')->value('id');
    $this->postJson("/api/v1/auth/passenger/notifications/{$otherUuid}/read")->assertNotFound();
    $this->postJson('/api/v1/auth/passenger/notifications/read-all')->assertOk()->assertJsonPath('data.unread', 0);
});

it('deduplicates passenger inbox events and keeps inbox persistence independent of push configuration', function (): void {
    Queue::fake();
    $passenger = User::factory()->create();
    Notification::sendNow($passenger, new PassengerInboxNotification('bus:delay:departure:eta-1', 'departure_delayed', 'Départ retardé', 'Votre départ a été retardé.', 'reservation-uuid'));
    Notification::sendNow($passenger, new PassengerInboxNotification('bus:delay:departure:eta-1', 'departure_delayed', 'Départ retardé', 'Votre départ a été retardé.', 'reservation-uuid'));

    expect($passenger->notifications()->where('audience', 'passenger')->count())->toBe(1)
        ->and($passenger->notifications()->where('audience', 'passenger')->first()->data['message'])->toBe('Votre départ a été retardé.');
});

it('registers an opted-in device token only for its owner and clears it when the account opts out', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $device = UserDevice::query()->create([
        'user_id' => $user->id,
        'name' => 'Android Passager',
        'platform' => DevicePlatform::Android,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'status' => UserDeviceStatus::Active,
    ]);
    $user->preference()->create(['push_notifications' => true, 'whatsapp_notifications' => true, 'email_notifications' => false, 'locale' => 'fr', 'theme' => 'system']);
    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->andReturn(true);
    $this->app->instance(FirebaseMessagingClient::class, $firebase);

    $this->actingAs($other)->putJson("/api/v1/auth/devices/{$device->uuid}/push-token", ['enabled' => true, 'push_token' => 'device-token'])->assertNotFound();
    $this->actingAs($user)->putJson("/api/v1/auth/devices/{$device->uuid}/push-token", ['enabled' => true, 'push_token' => 'device-token'])->assertOk()->assertJsonPath('data.enabled', true);
    $this->actingAs($user)->putJson('/api/v1/auth/preferences', ['push_notifications' => false])->assertOk();

    expect($device->refresh()->push_token)->toBeNull();
});

it('removes invalid passenger tokens and sends only the approved notification summary', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $user->preference()->create(['push_notifications' => true, 'whatsapp_notifications' => true, 'email_notifications' => false, 'locale' => 'fr', 'theme' => 'system']);
    $device = UserDevice::query()->create([
        'user_id' => $user->id,
        'name' => 'iPhone Passager',
        'platform' => DevicePlatform::Ios,
        'device_fingerprint_hash' => hash('sha256', Str::uuid()->toString()),
        'push_token' => 'invalid-fcm-token',
        'status' => UserDeviceStatus::Active,
    ]);
    Notification::sendNow($user, new PassengerInboxNotification('bus:reservation:private', 'reservation_confirmed', 'Réservation confirmée', 'Votre billet est disponible dans l’application.', 'reservation-uuid', ['reservation_reference' => 'SHOULD_NOT_PUSH']));
    $notificationUuid = $user->notifications()->where('audience', 'passenger')->value('id');

    $firebase = Mockery::mock(FirebaseMessagingClient::class);
    $firebase->shouldReceive('isServerConfigured')->once()->andReturn(true);
    $firebase->shouldReceive('send')->once()->with('invalid-fcm-token', Mockery::on(function (array $message): bool {
        $encoded = json_encode($message);

        return $message['title'] === 'Réservation confirmée'
            && $message['body'] === 'Votre billet est disponible dans l’application.'
            && ! str_contains((string) $encoded, 'SHOULD_NOT_PUSH')
            && ! str_contains((string) $encoded, 'reservation-uuid');
    }))->andReturn(false);

    (new SendPassengerPushJob($user->id, $notificationUuid))->handle($firebase);

    expect($device->refresh()->push_token)->toBeNull()
        ->and(DB::table('notification_channel_deliveries')->where('notification_uuid', $notificationUuid)->value('status'))->toBe('skipped');
});
