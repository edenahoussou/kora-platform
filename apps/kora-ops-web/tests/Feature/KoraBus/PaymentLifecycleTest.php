<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\PaymentManager;
use App\Modules\Payments\Providers\Fake\FakePaymentProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    // Bind a fake payment manager that returns the fake provider for all keys.
    $this->app->bind(PaymentManager::class, function (): PaymentManager {
        $manager = new PaymentManager;

        // Override resolve to always return the fake provider.
        $reflection = new ReflectionClass($manager);
        $property = $reflection->getProperty('resolved');
        $property->setValue($manager, [
            'fedapay' => new FakePaymentProvider,
            'mtn_momo' => new FakePaymentProvider,
            'fake' => new FakePaymentProvider,
        ]);

        return $manager;
    });

    FakePaymentProvider::reset();
});

it('requires authentication to initiate and poll payments', function (): void {
    $this->postJson('/api/v1/payments/initiate', [])->assertUnauthorized();
    $this->getJson('/api/v1/payments/'.Str::uuid())->assertUnauthorized();
});

it('initiates payment for held reservation', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $idempotencyKey = (string) Str::uuid();

    $response = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'phone' => '+2290197421860',
        'idempotency_key' => $idempotencyKey,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 15000)
        ->assertJsonPath('data.currency', 'XOF')
        ->assertJsonPath('data.provider', 'mtn_momo')
        ->assertJsonPath('data.polling_interval_seconds', 10);

    expect($response->json('data.provider_reference'))->not->toBeNull();

    $this->assertDatabaseHas('payments', [
        'reservation_id' => $scenario['reservation']->id,
        'idempotency_key' => $idempotencyKey,
        'status' => 'pending',
    ]);
});

it('is idempotent on payment initiation with same idempotency key', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $idempotencyKey = (string) Str::uuid();

    $firstRes = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'phone' => '+2290197421860',
        'idempotency_key' => $idempotencyKey,
    ])->assertCreated()->json();

    $secondRes = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'phone' => '+2290197421860',
        'idempotency_key' => $idempotencyKey,
    ])->assertOk()->json();

    expect($secondRes['data']['uuid'])->toBe($firstRes['data']['uuid']);
    expect(Payment::query()->where('idempotency_key', $idempotencyKey)->count())->toBe(1);
});

it('rejects initiation for expired reservation', function (): void {
    $scenario = paymentTestScenario();
    $scenario['reservation']->update(['expires_at' => now()->subMinute()]);
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reservation_uuid']);
});

it('forbids initiating payment on another user reservation', function (): void {
    $scenario = paymentTestScenario();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
});

it('polls payment status and includes reservation status', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-TEST1234',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    $response = $this->getJson("/api/v1/payments/{$payment->uuid}");

    $response->assertOk()
        ->assertJsonPath('data.uuid', $payment->uuid)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 15000)
        ->assertJsonPath('data.currency', 'XOF')
        ->assertJsonPath('data.provider_reference', 'MM-TEST1234')
        ->assertJsonPath('data.reservation.uuid', $scenario['reservation']->uuid)
        ->assertJsonPath('data.reservation.status', 'held')
        ->assertJsonPath('data.reservation.departure_uuid', $scenario['departure']->uuid);
});

it('forbids polling payment of another user', function (): void {
    $scenario = paymentTestScenario();
    $otherUser = User::factory()->create();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-TEST1234',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    Sanctum::actingAs($otherUser);
    $this->getJson("/api/v1/payments/{$payment->uuid}")->assertForbidden();
});

it('confirms payment via webhook and updates reservation and segments to paid', function (): void {
    $scenario = paymentTestScenario();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-WEBHOOK1',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    FakePaymentProvider::$nextStatus = PaymentStatus::Confirmed;

    $response = $this->postJson("/api/v1/payments/{$payment->uuid}/confirm", [
        'status' => 'success',
        'provider_reference' => 'MM-WEBHOOK1',
        'provider_payload' => ['transaction_id' => 'TRX-998877'],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('payment_uuid', $payment->uuid)
        ->assertJsonPath('reservation_uuid', $scenario['reservation']->uuid)
        ->assertJsonPath('reservation_status', 'paid');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);
    expect($payment->fresh()->confirmed_at)->not->toBeNull();
    expect($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Paid);
    expect($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Paid);
});

it('fails payment via webhook and releases segments/expires reservation', function (): void {
    $scenario = paymentTestScenario();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-WEBHOOK2',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    FakePaymentProvider::$nextStatus = PaymentStatus::Failed;

    $response = $this->postJson("/api/v1/payments/{$payment->uuid}/confirm", [
        'status' => 'failed',
        'provider_reference' => 'MM-WEBHOOK2',
        'provider_payload' => ['error_code' => 'INSUFFICIENT_FUNDS'],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('payment_uuid', $payment->uuid)
        ->assertJsonPath('reservation_uuid', $scenario['reservation']->uuid)
        ->assertJsonPath('reservation_status', 'expired');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed);
    expect($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Expired);
    expect($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Expired);
    expect($scenario['segment']->fresh()->released_at)->not->toBeNull();
});

it('rejects a successful webhook received after the reservation hold expired', function (): void {
    $scenario = paymentTestScenario();
    $scenario['reservation']->update(['expires_at' => now()->subMinute()]);

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-LATEWEBHOOK',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    FakePaymentProvider::$nextStatus = PaymentStatus::Confirmed;

    $response = $this->postJson("/api/v1/payments/{$payment->uuid}/confirm", [
        'status' => 'success',
        'provider_reference' => 'MM-LATEWEBHOOK',
        'provider_payload' => ['transaction_id' => 'TRX-LATE'],
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('reservation_status', 'expired');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($payment->fresh()->provider_payload['rejection_reason'])->toBe('reservation_expired')
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Expired)
        ->and($scenario['segment']->fresh()->status)->toBe(ReservationSegmentStatus::Expired)
        ->and($scenario['segment']->fresh()->released_at)->not->toBeNull();
});

it('hides numeric IDs in payment responses', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $initPayload = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->json();

    $paymentUuid = $initPayload['data']['uuid'];

    $pollPayload = $this->getJson("/api/v1/payments/{$paymentUuid}")
        ->assertOk()->json();

    foreach ([$initPayload, $pollPayload] as $payload) {
        $flattened = Arr::dot($payload);
        foreach (array_keys($flattened) as $key) {
            expect($key)->not->toEndWith('.id')
                ->and($key)->not->toBe('id');
        }
    }
});

it('routes payment to the correct provider via PaymentManager', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'fedapay',
        'phone' => '+2290197421860',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'fedapay')
        ->assertJsonPath('data.status', 'pending');

    expect($response->json('data.provider_reference'))->not->toBeNull();
});

it('rejects webhook with invalid signature for supported provider', function (): void {
    $scenario = paymentTestScenario();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'fedapay',
        'provider_reference' => 'FEDA-123',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    // Override the fake to return null (invalid signature).
    FakePaymentProvider::$webhookValid = false;

    $response = $this->postJson("/api/v1/payments/{$payment->uuid}/confirm", [
        'status' => 'success',
    ]);

    $response->assertForbidden()
        ->assertJsonPath('error', 'Invalid webhook signature.');
});

it('handles FedaPay webhook via dedicated endpoint', function (): void {
    $scenario = paymentTestScenario();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'fedapay',
        'provider_reference' => 'FEDA-WH1',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    FakePaymentProvider::$nextStatus = PaymentStatus::Confirmed;
    FakePaymentProvider::$webhookPayload = [
        'custom_metadata' => ['kora_payment_uuid' => $payment->uuid],
        'status' => 'approved',
    ];

    $response = $this->postJson('/api/v1/payments/webhook/fedapay', [
        'custom_metadata' => ['kora_payment_uuid' => $payment->uuid],
        'status' => 'approved',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('payment_uuid', $payment->uuid);
});

it('handles MTN MoMo webhook via dedicated endpoint', function (): void {
    $scenario = paymentTestScenario();

    $payment = Payment::create([
        'reservation_id' => $scenario['reservation']->id,
        'provider' => 'mtn_momo',
        'provider_reference' => 'MM-WH1',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 15000,
        'currency' => 'XOF',
        'status' => PaymentStatus::Pending,
    ]);

    FakePaymentProvider::$nextStatus = PaymentStatus::Confirmed;
    FakePaymentProvider::$webhookPayload = [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
    ];

    $response = $this->postJson('/api/v1/payments/webhook/mtn-momo', [
        'externalId' => $payment->uuid,
        'status' => 'SUCCESSFUL',
    ]);

    $response->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonPath('payment_uuid', $payment->uuid);
});

it('rejects webhook when payment reference not found in payload', function (): void {
    FakePaymentProvider::$webhookPayload = ['status' => 'approved'];

    $response = $this->postJson('/api/v1/payments/webhook/fedapay', [
        'status' => 'approved',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('error', 'Payment reference not found in webhook.');
});

it('rejects webhook for non-existent payment', function (): void {
    FakePaymentProvider::$webhookPayload = [
        'custom_metadata' => ['kora_payment_uuid' => Str::uuid()],
        'status' => 'approved',
    ];

    $response = $this->postJson('/api/v1/payments/webhook/fedapay', [
        'custom_metadata' => ['kora_payment_uuid' => Str::uuid()],
        'status' => 'approved',
    ]);

    $response->assertStatus(404)
        ->assertJsonPath('error', 'Payment not found.');
});

it('does not expose provider credentials in payment response', function (): void {
    $scenario = paymentTestScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'phone' => '+2290197421860',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertCreated();

    $flattened = Arr::dot($response->json());
    foreach (array_keys($flattened) as $key) {
        expect($key)->not->toContain('api_key')
            ->and($key)->not->toContain('secret')
            ->and($key)->not->toContain('subscription_key')
            ->and($key)->not->toContain('password');
    }
});

/**
 * @return array<string, mixed>
 */
function paymentTestScenario(): array
{
    $user = User::factory()->create();

    $line = Line::query()->firstOrCreate(
        ['code' => 'PAY-L1'],
        [
            'name' => 'Cotonou - Parakou Payment',
            'countries_crossed' => ['BJ'],
            'distance_km' => 450,
            'estimated_duration_minutes' => 360,
            'status' => 'active',
        ],
    );

    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['name' => 'Origin PAY', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['name' => 'Dest PAY', 'city' => 'Parakou', 'country' => 'BJ', 'km_from_origin' => 450, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'PAY-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach PAY', 'seat_count' => 1, 'status' => 'active'],
    );

    $seat1A = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '1A'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-22', 'departure_time' => '07:00'],
        ['estimated_arrival_at' => CarbonImmutable::parse('2026-08-22 13:00'), 'status' => 'scheduled'],
    );

    $reservation = Reservation::create([
        'user_id' => $user->id,
        'departure_id' => $departure->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);

    $segment = ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat1A->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => $origin->stop_order,
        'destination_stop_order' => $destination->stop_order,
        'status' => ReservationSegmentStatus::Held,
    ]);

    return compact('user', 'line', 'origin', 'destination', 'bus', 'seat1A', 'departure', 'reservation', 'segment');
}
