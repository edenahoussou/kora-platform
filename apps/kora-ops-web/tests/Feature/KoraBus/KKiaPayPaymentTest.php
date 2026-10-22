<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\IntegrationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Kkiapay\Kkiapay;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

beforeEach(function (): void {
    $settings = app(IntegrationsSettings::class);
    $settings->fedapayEnabled = false;
    $settings->mtnMomoEnabled = false;
    $settings->kkiapayEnabled = true;
    $settings->kkiapayPublicKey = 'kpk-test-public';
    $settings->kkiapayPrivateKey = 'ksk-test-private';
    $settings->kkiapaySecretKey = 'ksek-test-secret';
    $settings->kkiapayEnvironment = 'sandbox';
    $settings->kkiapayCountries = ['BJ', 'TG', 'CI'];
    $settings->kkiapayWebhookSecret = 'webhook-secret';
    $settings->save();
});

it('exposes kkiapay as a widget provider in the catalog', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/payments/methods')
        ->assertOk()
        ->assertJsonPath('data.countries', ['BJ', 'TG', 'CI'])
        ->assertJsonPath('data.methods.0.code', 'mobile_money')
        ->assertJsonPath('data.methods.0.providers.0.code', 'kkiapay')
        ->assertJsonPath('data.methods.0.providers.0.interaction', 'sdk_widget')
        ->assertJsonPath('data.methods.0.providers.0.countries', ['BJ', 'TG', 'CI'])
        ->assertJsonPath('data.methods.1.code', 'card')
        ->assertJsonPath('data.methods.1.enabled', true)
        ->assertJsonMissingPath('data.methods.0.providers.0.private_key');
});

it('initiates a kkiapay payment and returns the widget configuration', function (): void {
    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'mobile_money',
        'provider' => 'kkiapay',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.provider', 'kkiapay')
        ->assertJsonPath('data.widget.key', 'kpk-test-public')
        ->assertJsonPath('data.widget.sandbox', true)
        ->assertJsonPath('data.widget.amount', 15000)
        ->assertJsonPath('data.widget.paymentmethod', ['momo', 'card', 'wallet'])
        ->assertJsonPath('data.widget.countries', ['BJ', 'TG', 'CI']);

    expect($response->json('data.widget.partnerId'))
        ->toBe($response->json('data.uuid'));
});

it('initiates a card payment through the kkiapay widget', function (): void {
    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'card',
        'provider' => 'kkiapay',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()
        ->assertJsonPath('data.method', 'card')
        ->assertJsonPath('data.provider', 'kkiapay');

    $this->assertDatabaseHas('payments', ['method' => 'card', 'provider' => 'kkiapay']);
});

it('rejects card payments on providers that do not support them', function (): void {
    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $this->postJson('/api/v1/payments/initiate', [
        'reservation_uuid' => $scenario['reservation']->uuid,
        'method' => 'card',
        'provider' => 'mtn_momo',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable();
});

it('stays pending while the widget transactionId is unknown', function (): void {
    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = kkiapayPayment($scenario['reservation']);

    $this->postJson("/api/v1/payments/{$payment->uuid}/verify")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('confirms the payment when KKiaPay reports SUCCESS', function (): void {
    $this->instance(Kkiapay::class, Mockery::mock(Kkiapay::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verifyTransaction')->once()->with('kkp-tx-1')->andReturn((object) [
            'status' => 'SUCCESS',
            'source' => 'MOBILE_MONEY',
            'source_common_name' => 'mtn-benin',
            'amount' => 15000,
            'transactionId' => 'kkp-tx-1',
            'partnerId' => null,
        ]);
    }));

    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = kkiapayPayment($scenario['reservation']);

    $this->postJson("/api/v1/payments/{$payment->uuid}/verify", [
        'transaction_id' => 'kkp-tx-1',
    ])->assertOk()->assertJsonPath('data.status', 'confirmed');

    expect($payment->fresh()->provider_transaction_id)->toBe('kkp-tx-1')
        ->and($scenario['reservation']->fresh()->status)->toBe(ReservationStatus::Paid);
});

it('fails the payment when KKiaPay reports FAILED', function (): void {
    $this->instance(Kkiapay::class, Mockery::mock(Kkiapay::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verifyTransaction')->once()->with('kkp-tx-2')->andReturn((object) [
            'status' => 'FAILED',
            'failureCode' => 'insufficient_funds',
            'transactionId' => 'kkp-tx-2',
            'partnerId' => null,
        ]);
    }));

    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = kkiapayPayment($scenario['reservation']);

    $this->postJson("/api/v1/payments/{$payment->uuid}/verify", [
        'transaction_id' => 'kkp-tx-2',
    ])->assertOk()->assertJsonPath('data.status', 'failed');
});

it('does not confirm a transaction bound to another payment', function (): void {
    $this->instance(Kkiapay::class, Mockery::mock(Kkiapay::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verifyTransaction')->once()->with('kkp-tx-3')->andReturn((object) [
            'status' => 'SUCCESS',
            'transactionId' => 'kkp-tx-3',
            'partnerId' => (string) Str::uuid(),
        ]);
    }));

    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = kkiapayPayment($scenario['reservation']);

    $this->postJson("/api/v1/payments/{$payment->uuid}/verify", [
        'transaction_id' => 'kkp-tx-3',
    ])->assertOk()->assertJsonPath('data.status', 'pending');
});

it('rejects a transactionId different from the one already bound', function (): void {
    $scenario = kkiapayScenario();
    Sanctum::actingAs($scenario['user']);

    $payment = kkiapayPayment($scenario['reservation']);
    $payment->update(['provider_transaction_id' => 'kkp-tx-bound']);

    $this->postJson("/api/v1/payments/{$payment->uuid}/verify", [
        'transaction_id' => 'kkp-tx-other',
    ])->assertUnprocessable();
});

it('rejects kkiapay webhooks with a wrong secret', function (): void {
    $scenario = kkiapayScenario();
    $payment = kkiapayPayment($scenario['reservation']);

    $this->postJson('/api/v1/payments/webhook/kkiapay', [
        'transactionId' => 'kkp-tx-4',
        'partnerId' => $payment->uuid,
        'event' => 'transaction.success',
        'isPaymentSucces' => true,
    ], ['x-kkiapay-secret' => 'wrong-secret'])->assertForbidden();
});

it('confirms via webhook and stays idempotent on duplicates', function (): void {
    $scenario = kkiapayScenario();
    $payment = kkiapayPayment($scenario['reservation']);

    $this->instance(Kkiapay::class, Mockery::mock(Kkiapay::class, function (MockInterface $mock) use ($payment): void {
        $mock->shouldReceive('verifyTransaction')->with('kkp-tx-5')->andReturn((object) [
            'status' => 'SUCCESS',
            'transactionId' => 'kkp-tx-5',
            'partnerId' => $payment->uuid,
            'amount' => 15000,
        ]);
    }));

    $payload = [
        'transactionId' => 'kkp-tx-5',
        'partnerId' => $payment->uuid,
        'event' => 'transaction.success',
        'isPaymentSucces' => true,
        'method' => 'MOBILE_MONEY',
        'amount' => 15000,
    ];

    $this->postJson('/api/v1/payments/webhook/kkiapay', $payload, [
        'x-kkiapay-secret' => 'webhook-secret',
    ])->assertOk()->assertJsonPath('status', 'confirmed');

    expect($payment->fresh()->provider_transaction_id)->toBe('kkp-tx-5')
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Confirmed);

    $this->postJson('/api/v1/payments/webhook/kkiapay', $payload, [
        'x-kkiapay-secret' => 'webhook-secret',
    ])->assertOk()->assertJsonPath('status', 'confirmed');
});

/**
 * @return array<string, mixed>
 */
function kkiapayScenario(): array
{
    $user = User::factory()->create();

    $line = Line::query()->firstOrCreate(
        ['code' => 'KKP-L1'],
        [
            'name' => 'Cotonou - Parakou KKiaPay',
            'countries_crossed' => ['BJ'],
            'distance_km' => 450,
            'estimated_duration_minutes' => 360,
            'status' => 'active',
        ],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Origin KKP Physical'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'Origin KKP', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Dest KKP Physical'],
        ['city' => 'Parakou', 'country' => 'BJ', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'Dest KKP', 'city' => 'Parakou', 'country' => 'BJ', 'km_from_origin' => 450, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'KKP-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach KKP', 'seat_count' => 1, 'status' => 'active'],
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
        'created_by_user_id' => $user->id,
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

function kkiapayPayment(Reservation $reservation): Payment
{
    return Payment::create([
        'reservation_id' => $reservation->id,
        'method' => 'mobile_money',
        'provider' => 'kkiapay',
        'idempotency_key' => (string) Str::uuid(),
        'request_fingerprint' => hash('sha256', 'kkiapay-test'),
        'amount' => $reservation->total_amount,
        'currency' => $reservation->currency,
        'status' => PaymentStatus::Pending,
    ]);
}
