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
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('requires authentication to create quotes and holds', function (): void {
    $this->postJson('/api/v1/bus/quotes', [])->assertUnauthorized();
    $this->postJson('/api/v1/bus/holds', [])->assertUnauthorized();
});

it('creates a fixed price quote with dynamic pricing and 10min expiration', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'passenger_count' => 2,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.departure_uuid', $scenario['departure']->uuid)
        ->assertJsonPath('data.base_amount', 10000)
        ->assertJsonPath('data.coefficient', 1.1)
        ->assertJsonPath('data.final_amount', 11000)
        ->assertJsonPath('data.currency', 'XOF')
        ->assertJsonPath('data.passenger_count', 2)
        ->assertJsonPath('data.total_amount', 22000)
        ->assertJsonPath('data.snapshot.line_code', 'BK-L1')
        ->assertJsonPath('data.snapshot.origin_stop', 'Cotonou BK')
        ->assertJsonPath('data.snapshot.destination_stop', 'Abidjan BK');

    $expiresAt = $response->json('data.expires_at');
    expect($expiresAt)->not->toBeNull();
    expect(CarbonImmutable::parse($expiresAt)->greaterThan(now()->addMinutes(9)))->toBeTrue();
});

it('validates segment stops when creating a quote', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    // Reverse order
    $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['destination']->uuid,
        'destination_stop_uuid' => $scenario['origin']->uuid,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['destination_stop_uuid']);
});

it('creates a seat hold for 15 minutes', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $quote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1', 'passenger_count' => 1],
        'expires_at' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'held')
        ->assertJsonPath('data.total_amount', 10000)
        ->assertJsonPath('data.currency', 'XOF')
        ->assertJsonPath('data.channel', 'mobile')
        ->assertJsonPath('data.departure_uuid', $scenario['departure']->uuid)
        ->assertJsonCount(1, 'data.segments')
        ->assertJsonPath('data.segments.0.seat_code', '2B')
        ->assertJsonPath('data.segments.0.status', 'held');

    $this->assertDatabaseHas('reservations', [
        'user_id' => $scenario['user']->id,
        'departure_id' => $scenario['departure']->id,
        'status' => 'held',
    ]);
});

it('returns 409 conflict when requesting an already occupied or blocked seat', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $quote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->addMinutes(10),
    ]);

    // Seat 2A is already occupied on Cotonou -> Lome
    $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2A']->uuid],
        ],
    ])->assertStatus(409)
        ->assertJsonPath('seat_code', '2A')
        ->assertJsonPath('conflicting_seat_uuid', $scenario['seat2A']->uuid);

    // Seat 1A is blocked/crew
    $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat1A']->uuid],
        ],
    ])->assertStatus(409);
});

it('allows holding seat on non-overlapping segment', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    // Seat 2A is occupied on Cotonou (1) -> Lome (2)
    // Requesting Lome (2) -> Abidjan (3) should succeed!
    $fare = Fare::query()->firstOrCreate(
        ['line_id' => $scenario['line']->id, 'origin_stop_id' => $scenario['middle']->id, 'destination_stop_id' => $scenario['destination']->id],
        ['base_amount' => 6000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active'],
    );

    $quote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['middle']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $fare->id,
        'base_amount' => 6000,
        'coefficient' => 1.0,
        'final_amount' => 6000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['middle']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2A']->uuid],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.segments.0.seat_code', '2A');
});

it('rejects hold with expired quote', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $expiredQuote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $expiredQuote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['price_quote_uuid']);
});

it('expires past-due holds with bus:expire-holds command', function (): void {
    $scenario = bookingTestScenario();

    $reservation = Reservation::create([
        'user_id' => $scenario['user']->id,
        'departure_id' => $scenario['departure']->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 10000,
        'currency' => 'XOF',
        'expires_at' => now()->subMinutes(5),
    ]);

    $segment = ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'departure_id' => $scenario['departure']->id,
        'seat_id' => $scenario['seat2B']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'origin_stop_order' => $scenario['origin']->stop_order,
        'destination_stop_order' => $scenario['destination']->stop_order,
        'status' => ReservationSegmentStatus::Held,
    ]);

    $this->artisan('bus:expire-holds')
        ->expectsOutputToContain('Expired 1 seat hold(s).')
        ->assertSuccessful();

    expect($reservation->fresh()->status)->toBe(ReservationStatus::Expired);
    expect($segment->fresh()->status)->toBe(ReservationSegmentStatus::Expired);
    expect($segment->fresh()->released_at)->not->toBeNull();
});

it('hides internal numeric IDs in quote and hold responses', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $quoteRes = $this->postJson('/api/v1/bus/quotes', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ])->assertCreated()->json();

    $quoteUuid = $quoteRes['data']['uuid'];

    $holdRes = $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quoteUuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertCreated()->json();

    foreach ([$quoteRes, $holdRes] as $payload) {
        $flattened = Arr::dot($payload);
        foreach (array_keys($flattened) as $key) {
            expect($key)->not->toEndWith('.id')
                ->and($key)->not->toBe('id');
        }
    }
});

it('requires authentication to release a seat hold', function (): void {
    $scenario = bookingTestScenario();

    $reservation = Reservation::create([
        'user_id' => $scenario['user']->id,
        'departure_id' => $scenario['departure']->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 10000,
        'currency' => 'XOF',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->deleteJson("/api/v1/bus/holds/{$reservation->uuid}")->assertUnauthorized();

    $this->assertDatabaseHas('reservations', [
        'uuid' => $reservation->uuid,
        'status' => ReservationStatus::Held->value,
    ]);
});

it('releases a seat hold and frees the seat for a new hold', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    $quote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $reservation = $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertCreated();

    $reservationUuid = $reservation->json('data.uuid');

    $this->deleteJson("/api/v1/bus/holds/{$reservationUuid}")
        ->assertNoContent();

    $this->assertDatabaseHas('reservations', [
        'uuid' => $reservationUuid,
        'status' => ReservationStatus::Cancelled->value,
    ]);

    $freshSegment = ReservationSegment::query()
        ->whereHas('reservation', fn ($query) => $query->where('uuid', $reservationUuid))
        ->first();
    expect($freshSegment->status)->toBe(ReservationSegmentStatus::Cancelled);
    expect($freshSegment->released_at)->not->toBeNull();

    // The seat is now free again: a new hold on the same segment succeeds.
    $newQuote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $newQuote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertCreated();
});

it('prevents releasing another users seat hold', function (): void {
    $scenario = bookingTestScenario();
    $otherUser = User::factory()->create();

    Sanctum::actingAs($scenario['user']);

    $quote = PriceQuote::create([
        'departure_id' => $scenario['departure']->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'fare_id' => $scenario['fare']->id,
        'base_amount' => 10000,
        'coefficient' => 1.0,
        'final_amount' => 10000,
        'currency' => 'XOF',
        'snapshot' => ['line_code' => 'BK-L1'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $reservationUuid = $this->postJson('/api/v1/bus/holds', [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
        'price_quote_uuid' => $quote->uuid,
        'seats' => [
            ['seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertCreated()->json('data.uuid');

    Sanctum::actingAs($otherUser);

    $this->deleteJson("/api/v1/bus/holds/{$reservationUuid}")->assertForbidden();

    $this->assertDatabaseHas('reservations', [
        'uuid' => $reservationUuid,
        'status' => ReservationStatus::Held->value,
    ]);
});

it('returns 404 when releasing an unknown hold uuid', function (): void {
    $scenario = bookingTestScenario();
    Sanctum::actingAs($scenario['user']);

    // ✅ CORRECTION : Utilisation de Str::uuid() sans le préfixe \Illuminate\Support\
    $this->deleteJson('/api/v1/bus/holds/'.Str::uuid())
        ->assertNotFound();
});

/**
 * @return array<string, mixed>
 */
function bookingTestScenario(): array
{
    $user = User::factory()->create();

    $line = Line::query()->firstOrCreate(
        ['code' => 'BK-L1'],
        [
            'name' => 'Cotonou - Abidjan Booking',
            'countries_crossed' => ['BJ', 'TG', 'CI'],
            'distance_km' => 1020,
            'estimated_duration_minutes' => 1260,
            'status' => 'active',
        ],
    );

    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['name' => 'Cotonou BK', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $middle = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['name' => 'Lome BK', 'city' => 'Lome', 'country' => 'TG', 'km_from_origin' => 170, 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 3],
        ['name' => 'Abidjan BK', 'city' => 'Abidjan', 'country' => 'CI', 'km_from_origin' => 1020, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'BK-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach BK', 'seat_count' => 3, 'status' => 'active'],
    );

    $seat1A = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '1A'],
        ['type' => 'crew', 'sellable' => false, 'status' => 'blocked'],
    );
    $seat2A = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2A'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );
    $seat2B = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2B'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-20', 'departure_time' => '08:00'],
        ['estimated_arrival_at' => CarbonImmutable::parse('2026-08-20 20:00'), 'status' => 'scheduled'],
    );

    $fare = Fare::query()->firstOrCreate(
        ['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id],
        ['base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active'],
    );

    // Add dynamic pricing rule (+10%)
    PricingRule::query()->firstOrCreate(
        ['line_id' => $line->id, 'type' => PricingRuleType::Increase->value],
        [
            'percentage' => 10.0,
            'priority' => 10,
            'status' => 'active',
            'conditions' => [],
        ],
    );

    // Existing reservation on segment 1->2 for seat 2A
    $existingRes = Reservation::query()->firstOrCreate(
        ['departure_id' => $departure->id, 'status' => 'confirmed'],
        ['total_amount' => 5000, 'currency' => 'XOF', 'channel' => 'online'],
    );

    ReservationSegment::query()->firstOrCreate(
        ['reservation_id' => $existingRes->id, 'seat_id' => $seat2A->id],
        [
            'departure_id' => $departure->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $middle->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $middle->stop_order,
            'status' => 'confirmed',
        ],
    );

    return compact('user', 'line', 'origin', 'middle', 'destination', 'bus', 'seat1A', 'seat2A', 'seat2B', 'departure', 'fare');
}
