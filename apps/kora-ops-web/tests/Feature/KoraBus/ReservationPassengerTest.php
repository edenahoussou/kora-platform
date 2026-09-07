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
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Sanctum;

it('requires authentication to store passengers', function (): void {
    $this->postJson('/api/v1/bookings/9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb70/passengers', [])
        ->assertUnauthorized();
});

it('stores passenger details and links to reservation segments', function (): void {
    $scenario = passengerTestScenario(isInternational: false);
    Sanctum::actingAs($scenario['user']);

    $response = $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            [
                'first_name' => 'Aïcha',
                'last_name' => 'HOUNKPÈ',
                'phone' => '+2290197421860',
                'email' => 'aicha@example.com',
                'seat_uuid' => $scenario['seat2A']->uuid,
            ],
            [
                'first_name' => 'Koffi',
                'last_name' => 'MENSAH',
                'phone' => '+2290197421861',
                'seat_uuid' => $scenario['seat2B']->uuid,
            ],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.uuid', $scenario['reservation']->uuid)
        ->assertJsonPath('data.status', 'held')
        ->assertJsonCount(2, 'data.passengers')
        ->assertJsonPath('data.passengers.0.first_name', 'Aïcha')
        ->assertJsonPath('data.passengers.0.last_name', 'HOUNKPÈ')
        ->assertJsonPath('data.passengers.0.seat_code', '2A')
        ->assertJsonPath('data.passengers.1.first_name', 'Koffi')
        ->assertJsonPath('data.passengers.1.seat_code', '2B');

    $this->assertDatabaseHas('reservation_passengers', [
        'reservation_id' => $scenario['reservation']->id,
        'first_name' => 'Aïcha',
        'last_name' => 'HOUNKPÈ',
    ]);
});

it('accepts a minimal international passenger profile and validates document pairs', function (): void {
    $scenario = passengerTestScenario(isInternational: true);
    Sanctum::actingAs($scenario['user']);

    // Identity alone is sufficient for an international sale.
    $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            [
                'first_name' => 'Aïcha',
                'last_name' => 'HOUNKPÈ',
                'seat_uuid' => $scenario['seat2A']->uuid,
            ],
            [
                'first_name' => 'Koffi',
                'last_name' => 'MENSAH',
                'seat_uuid' => $scenario['seat2B']->uuid,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.passengers.0.document_type', null);

    // Valid international submission
    $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            [
                'first_name' => 'Aïcha',
                'last_name' => 'HOUNKPÈ',
                'document_type' => 'passport',
                'document_number' => 'BJ0427851',
                'nationality' => 'BJ',
                'birth_date' => '1994-04-18',
                'seat_uuid' => $scenario['seat2A']->uuid,
            ],
            [
                'first_name' => 'Koffi',
                'last_name' => 'MENSAH',
                'document_type' => 'national_id',
                'document_number' => 'BJ999888',
                'nationality' => 'BJ',
                'birth_date' => '1990-01-01',
                'seat_uuid' => $scenario['seat2B']->uuid,
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.passengers.0.document_type', 'passport')
        ->assertJsonPath('data.passengers.0.document_number', 'BJ0427851')
        ->assertJsonPath('data.passengers.0.nationality', 'BJ');
});

it('rejects passenger submission if counts do not match segments count', function (): void {
    $scenario = passengerTestScenario(isInternational: false);
    Sanctum::actingAs($scenario['user']);

    // Only 1 passenger for 2 seats
    $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            [
                'first_name' => 'Aïcha',
                'last_name' => 'HOUNKPÈ',
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['passengers']);
});

it('forbids updating another user reservation passengers', function (): void {
    $scenario = passengerTestScenario(isInternational: false);
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            ['first_name' => 'Aïcha', 'last_name' => 'HOUNKPÈ'],
            ['first_name' => 'Koffi', 'last_name' => 'MENSAH'],
        ],
    ])->assertForbidden();
});

it('rejects passenger submission on expired reservation', function (): void {
    $scenario = passengerTestScenario(isInternational: false);
    $scenario['reservation']->update(['expires_at' => now()->subMinute()]);
    Sanctum::actingAs($scenario['user']);

    $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            ['first_name' => 'Aïcha', 'last_name' => 'HOUNKPÈ'],
            ['first_name' => 'Koffi', 'last_name' => 'MENSAH'],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['reservation']);
});

it('hides numeric IDs in passenger response', function (): void {
    $scenario = passengerTestScenario(isInternational: false);
    Sanctum::actingAs($scenario['user']);

    $payload = $this->postJson("/api/v1/bookings/{$scenario['reservation']->uuid}/passengers", [
        'passengers' => [
            ['first_name' => 'Aïcha', 'last_name' => 'HOUNKPÈ', 'seat_uuid' => $scenario['seat2A']->uuid],
            ['first_name' => 'Koffi', 'last_name' => 'MENSAH', 'seat_uuid' => $scenario['seat2B']->uuid],
        ],
    ])->assertOk()->json();

    $flattened = Arr::dot($payload);
    foreach (array_keys($flattened) as $key) {
        expect($key)->not->toEndWith('.id')
            ->and($key)->not->toBe('id');
    }
});

/**
 * @return array<string, mixed>
 */
function passengerTestScenario(bool $isInternational = false): array
{
    $user = User::factory()->create();

    $line = Line::query()->firstOrCreate(
        ['code' => $isInternational ? 'PS-L-INT' : 'PS-L-DOM'],
        [
            'name' => $isInternational ? 'Cotonou - Lome INT' : 'Cotonou - Parakou DOM',
            'countries_crossed' => $isInternational ? ['BJ', 'TG'] : ['BJ'],
            'distance_km' => 450,
            'estimated_duration_minutes' => 360,
            'status' => 'active',
        ],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Origin PS Physical'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'Origin PS', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => $isInternational ? 'Lome PS Physical' : 'Parakou PS Physical'],
        ['city' => $isInternational ? 'Lome' : 'Parakou', 'country' => $isInternational ? 'TG' : 'BJ', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'Dest PS', 'city' => $isInternational ? 'Lome' : 'Parakou', 'country' => $isInternational ? 'TG' : 'BJ', 'km_from_origin' => 450, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'PS-BUS-'.($isInternational ? '001' : '002')],
        ['brand' => 'KORA', 'model' => 'Coach PS', 'seat_count' => 2, 'status' => 'active'],
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
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-21', 'departure_time' => '09:00'],
        ['estimated_arrival_at' => CarbonImmutable::parse('2026-08-21 15:00'), 'status' => 'scheduled'],
    );

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'status' => ReservationStatus::Held,
        'total_amount' => 20000,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => now()->addMinutes(15),
    ]);

    $segment1 = ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat2A->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => $origin->stop_order,
        'destination_stop_order' => $destination->stop_order,
        'status' => ReservationSegmentStatus::Held,
    ]);

    $segment2 = ReservationSegment::create([
        'reservation_id' => $reservation->id,
        'departure_id' => $departure->id,
        'seat_id' => $seat2B->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => $origin->stop_order,
        'destination_stop_order' => $destination->stop_order,
        'status' => ReservationSegmentStatus::Held,
    ]);

    return compact('user', 'line', 'origin', 'destination', 'bus', 'seat2A', 'seat2B', 'departure', 'reservation', 'segment1', 'segment2');
}
