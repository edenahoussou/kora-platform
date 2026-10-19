<?php

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\UserPreference;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use App\Modules\Notifications\Services\Sms\PassengerSmsTemplate;
use App\Modules\Notifications\Services\Sms\SmsText;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\IntegrationsSettings;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function smsPublisherSettings(): IntegrationsSettings
{
    $settings = new IntegrationsSettings;
    $settings->conexteoEnabled = true;
    $settings->conexteoAppId = 'app-123';
    $settings->conexteoApiKey = 'secret-api-key';
    $settings->conexteoSender = 'KoraExpress';

    return $settings;
}

function createSmsReservationScenario(
    string $payerPhone = '+22997000000',
    bool $withPayerAccount = true,
    PayerType $payerType = PayerType::Personal,
): array {
    $creator = User::factory()->create();
    $payer = User::factory()->create(['phone' => $payerPhone]);

    $line = Line::query()->create([
        'code' => 'SM-'.Str::upper(Str::random(6)),
        'name' => 'Cotonou - Parakou',
        'type' => 'nationale',
        'countries_crossed' => ['BJ'],
        'distance_km' => 450,
        'estimated_duration_minutes' => 420,
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => PhysicalStop::query()->create(['name' => 'SM Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'])->id,
        'name' => 'Gare Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => PhysicalStop::query()->create(['name' => 'SM Destination', 'city' => 'Parakou', 'country' => 'BJ', 'status' => 'active'])->id,
        'name' => 'Gare Parakou',
        'city' => 'Parakou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 450,
        'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'SM-'.Str::upper(Str::random(8)),
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 40,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => now()->addDays(4)->toDateString(),
        'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addDays(4)->addHours(6),
        'status' => 'scheduled',
    ]);

    $reservation = Reservation::query()->create([
        'created_by_user_id' => $creator->id,
        'payer_user_id' => $withPayerAccount ? $payer->id : null,
        'payer_type' => $payerType,
        'status' => ReservationStatus::Ticketed,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);
    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id,
        'user_id' => $withPayerAccount ? $payer->id : null,
        'first_name' => 'Awa',
        'last_name' => 'SMS',
        'passenger_index' => 0,
        'status' => 'active',
    ]);
    ReservationSegment::query()->create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'leg_order' => 0,
        'status' => ReservationSegmentStatus::Ticketed,
    ]);
    Payment::query()->create([
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'provider' => 'cash',
        'provider_reference' => 'CASH-SM-'.$reservation->id,
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Confirmed,
        'payer_phone' => $payerPhone,
        'confirmed_at' => now(),
    ]);
    Ticket::query()->create([
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $passenger->id,
        'qr_code' => 'QR-SM-'.$reservation->id,
        'short_code' => 'SM-'.Str::random(8),
        'status' => 'issued',
        'issued_at' => now(),
    ]);

    return compact('reservation', 'departure', 'payer');
}

function fakeConexteoSms(): void
{
    Http::preventStrayRequests();
    Http::fake([
        'api.conexteo.com/*' => Http::response([
            'success' => true,
            'message_id' => 'msg-'.Str::random(6),
            'credits_used' => 1,
        ]),
    ]);
}

it('sends a confirmation SMS to the payer and logs it', function (): void {
    app()->instance(IntegrationsSettings::class, smsPublisherSettings());
    fakeConexteoSms();

    $scenario = createSmsReservationScenario();

    app(PassengerNotificationPublisher::class)->reservationConfirmed($scenario['reservation']);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.conexteo.com/messages/sms'
        && $request['recipients'] === ['+22997000000']
        && str_contains((string) $request['content'], 'confirmé'));

    $log = NotificationLog::query()->where('channel', 'sms')->where('template', 'reservation_confirmed')->first();

    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->recipient)->toBe('+22997000000')
        ->and($log->sent_at)->not->toBeNull()
        ->and($log->payload['credits_used'])->toBe(1);
});

it('sends cancellation and delay SMS but not other passenger events', function (): void {
    app()->instance(IntegrationsSettings::class, smsPublisherSettings());
    fakeConexteoSms();

    $scenario = createSmsReservationScenario();
    $publisher = app(PassengerNotificationPublisher::class);

    $publisher->departureChanged($scenario['departure'], 'modified');
    expect(NotificationLog::query()->where('channel', 'sms')->count())->toBe(0);

    $publisher->departureChanged($scenario['departure'], 'cancelled');
    $publisher->departureDelayed($scenario['departure'], now()->addHour()->toIso8601String());

    expect(NotificationLog::query()->where('channel', 'sms')->pluck('template')->all())
        ->toContain('departure_cancelled')
        ->toContain('departure_delayed');
});

it('sends the SMS to a company payer without an account', function (): void {
    app()->instance(IntegrationsSettings::class, smsPublisherSettings());
    fakeConexteoSms();

    $scenario = createSmsReservationScenario(
        payerPhone: '+22996000000',
        withPayerAccount: false,
        payerType: PayerType::Company,
    );

    app(PassengerNotificationPublisher::class)->reservationConfirmed($scenario['reservation']);

    expect(NotificationLog::query()->where('channel', 'sms')->where('status', 'sent')->exists())->toBeTrue();
});

it('skips the SMS when the known payer disabled sms notifications', function (): void {
    app()->instance(IntegrationsSettings::class, smsPublisherSettings());
    fakeConexteoSms();

    $scenario = createSmsReservationScenario();
    UserPreference::query()->create([
        'user_id' => $scenario['payer']->id,
        'push_notifications' => true,
        'whatsapp_notifications' => true,
        'sms_notifications' => false,
        'email_notifications' => true,
        'locale' => 'fr',
        'theme' => 'light',
    ]);

    app(PassengerNotificationPublisher::class)->reservationConfirmed($scenario['reservation']);

    Http::assertNothingSent();
    expect(NotificationLog::query()->where('channel', 'sms')->count())->toBe(0);
});

it('does not send the same SMS twice when the notification is replayed', function (): void {
    app()->instance(IntegrationsSettings::class, smsPublisherSettings());
    fakeConexteoSms();

    $scenario = createSmsReservationScenario();
    $publisher = app(PassengerNotificationPublisher::class);

    $publisher->reservationConfirmed($scenario['reservation']);
    $publisher->reservationConfirmed($scenario['reservation']);

    expect(NotificationLog::query()->where('channel', 'sms')->where('template', 'reservation_confirmed')->count())->toBe(1);
    Http::assertSentCount(1);
});

it('keeps every routed template GSM-7 and under one segment', function (): void {
    $scenario = createSmsReservationScenario();
    $template = app(PassengerSmsTemplate::class);

    $messages = [
        $template->build('reservation_confirmed', $scenario['reservation'], [
            'route' => 'Cotonou → Parakou',
            'tickets' => 3,
        ]),
        $template->build('departure_cancelled', $scenario['reservation'], [
            'route' => 'Cotonou → Parakou',
            'scheduled_at' => '23/10 à 08h00',
        ]),
        $template->build('departure_delayed', $scenario['reservation'], [
            'route' => 'Cotonou → Parakou',
            'eta' => '10h30',
        ]),
    ];

    foreach ($messages as $message) {
        expect($message)->toBeString()
            ->and(SmsText::isGsm7($message))->toBeTrue()
            ->and(mb_strlen($message))->toBeLessThanOrEqual(160)
            ->and($message)->not->toContain('→');
    }
});
