<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusAmenity;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-14 07:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('lists active cities with their physical stops for itinerary search', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/cities')
        ->assertOk()
        ->assertJsonFragment(['city' => 'Cotonou', 'country' => 'BJ'])
        ->assertJsonFragment(['city' => 'Abidjan', 'country' => 'CI'])
        ->assertJsonPath('data.0.physical_stops.0.uuid', fn (?string $uuid): bool => $uuid !== null)
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.physical_stops.0.id');
});

it('returns rich itinerary legs with line, bus, amenities, stops and service class', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.connections_count', 0)
        ->assertJsonPath('data.0.total_amount', 12500)
        ->assertJsonPath('data.0.currency', 'XOF')
        ->assertJsonPath('data.0.legs.0.leg_order', 1)
        ->assertJsonPath('data.0.legs.0.departure_uuid', $scenario['departure']->uuid)
        // Line block
        ->assertJsonPath('data.0.legs.0.line.uuid', $scenario['line']->uuid)
        ->assertJsonPath('data.0.legs.0.line.code', 'API-L1')
        ->assertJsonPath('data.0.legs.0.line.name', 'Cotonou - Abidjan API')
        ->assertJsonPath('data.0.legs.0.line.is_international', true)
        // Bus block
        ->assertJsonPath('data.0.legs.0.bus.uuid', $scenario['bus']->uuid)
        ->assertJsonPath('data.0.legs.0.bus.registration_number', 'API-BUS-001')
        ->assertJsonPath('data.0.legs.0.bus.seat_count', 3)
        ->assertJsonPath('data.0.legs.0.bus.amenities.0.code', 'wifi')
        // Stops with city/country
        ->assertJsonPath('data.0.legs.0.origin_stop.uuid', $scenario['origin']->uuid)
        ->assertJsonPath('data.0.legs.0.origin_stop.city', 'Cotonou')
        ->assertJsonPath('data.0.legs.0.origin_stop.country', 'BJ')
        ->assertJsonPath('data.0.legs.0.destination_stop.uuid', $scenario['destination']->uuid)
        ->assertJsonPath('data.0.legs.0.destination_stop.city', 'Abidjan')
        ->assertJsonPath('data.0.legs.0.destination_stop.country', 'CI')
        // Fare + availability + service class
        ->assertJsonPath('data.0.legs.0.amount', 12500)
        ->assertJsonPath('data.0.legs.0.available_seats_count', 1)
        ->assertJsonPath('data.0.legs.0.service_class_code', 'api-standard')
        ->assertJsonPath('data.0.legs.0.service_class_name', 'Standard API')
        // No numeric ids leaked
        ->assertJsonMissingPath('data.0.legs.0.bus.id')
        ->assertJsonMissingPath('data.0.legs.0.origin_stop.id')
        ->assertJsonMissingPath('data.0.legs.0.destination_stop.id')
        ->assertJsonMissingPath('data.0.legs.0.line.id');
});

it('supports sorting by departure time and filtering by service class code', function (): void {
    $scenario = koraBusApiScenario();

    // Sort by departure_time asc — default secondary sort keeps direct first
    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&sort=departure_time&sort_direction=asc')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.legs.0.departure_uuid', $scenario['departure']->uuid);

    // Filter by the matching service class code — should still return the itinerary
    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&service_class_code=api-standard')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Filter by a non-matching service class code — should return empty
    $otherClass = ServiceClass::query()->where('code', '!=', 'api-standard')->first() ?? ServiceClass::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'api-premium',
        'name' => 'Premium API',
        'status' => 'active',
    ]);

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&service_class_code='.$otherClass->code)
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('rejects invalid sort and sort_direction parameters', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&sort=invalid')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&sort_direction=sideways')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort_direction']);
});

it('returns outbound and inbound itineraries when trip_type is round_trip', function (): void {
    $scenario = koraBusApiScenario();

    // Create a return departure on the same line (reversed direction)
    $returnDeparture = Departure::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'bus_id' => $scenario['bus']->id,
        'departure_date' => '2026-08-16',
        'departure_time' => '07:30',
        'estimated_arrival_at' => CarbonImmutable::parse('2026-08-16 17:15'),
        'status' => 'scheduled',
        'stop_times' => [
            ['stop_uuid' => $scenario['destination']->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Abidjan'],
            ['stop_uuid' => $scenario['middle']->uuid, 'arrival_minutes' => 621, 'departure_minutes' => 631, 'timezone' => 'Africa/Lome'],
            ['stop_uuid' => $scenario['origin']->uuid, 'arrival_minutes' => 1024, 'departure_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
        ],
    ]);

    // Fare for the return direction
    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['destination']->id,
        'destination_stop_id' => $scenario['origin']->id,
        'service_class_id' => ServiceClass::query()->where('code', 'api-standard')->firstOrFail()->id,
        'base_amount' => 12500,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $response = $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&trip_type=round_trip&return_date=2026-08-16')
        ->assertOk();

    $response->assertJsonStructure(['outbound', 'inbound'])
        ->assertJsonCount(1, 'outbound')
        ->assertJsonCount(1, 'inbound')
        ->assertJsonPath('outbound.0.legs.0.departure_uuid', $scenario['departure']->uuid)
        ->assertJsonPath('inbound.0.legs.0.departure_uuid', $returnDeparture->uuid)
        ->assertJsonMissingPath('data');
});

it('returns data key for one_way trip_type (retro-compatible)', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&trip_type=one_way')
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonMissingPath('outbound')
        ->assertJsonMissingPath('inbound');
});

it('accepts direction per leg in itinerary quotes for round trips', function (): void {
    $scenario = koraBusApiScenario();

    $user = User::factory()->create();

    // Create a return departure
    $returnDeparture = Departure::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'bus_id' => $scenario['bus']->id,
        'departure_date' => '2026-08-16',
        'departure_time' => '07:30',
        'estimated_arrival_at' => CarbonImmutable::parse('2026-08-16 17:15'),
        'status' => 'scheduled',
        'stop_times' => [
            ['stop_uuid' => $scenario['destination']->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Abidjan'],
            ['stop_uuid' => $scenario['middle']->uuid, 'arrival_minutes' => 621, 'departure_minutes' => 631, 'timezone' => 'Africa/Lome'],
            ['stop_uuid' => $scenario['origin']->uuid, 'arrival_minutes' => 1024, 'departure_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
        ],
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['destination']->id,
        'destination_stop_id' => $scenario['origin']->id,
        'service_class_id' => ServiceClass::query()->where('code', 'api-standard')->firstOrFail()->id,
        'base_amount' => 12500,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/bus/itinerary-quotes', [
            'passenger_count' => 1,
            'legs' => [
                [
                    'leg_order' => 1,
                    'direction' => 'outbound',
                    'departure_uuid' => $scenario['departure']->uuid,
                    'origin_stop_uuid' => $scenario['origin']->uuid,
                    'destination_stop_uuid' => $scenario['destination']->uuid,
                ],
                [
                    'leg_order' => 2,
                    'direction' => 'inbound',
                    'departure_uuid' => $returnDeparture->uuid,
                    'origin_stop_uuid' => $scenario['destination']->uuid,
                    'destination_stop_uuid' => $scenario['origin']->uuid,
                ],
            ],
        ])
        ->assertOk();

    $response->assertJsonPath('data.trip_type', 'round_trip')
        ->assertJsonPath('data.legs.0.direction', 'outbound')
        ->assertJsonPath('data.legs.1.direction', 'inbound')
        ->assertJsonPath('data.legs.0.leg_order', 1)
        ->assertJsonPath('data.legs.1.leg_order', 2);
});

it('searches upcoming return departures when trip_type is round_trip without return_date', function (): void {
    $scenario = koraBusApiScenario();

    // Create a return departure the day after outbound
    $returnDeparture = Departure::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'bus_id' => $scenario['bus']->id,
        'departure_date' => '2026-08-16',
        'departure_time' => '07:30',
        'estimated_arrival_at' => CarbonImmutable::parse('2026-08-16 17:15'),
        'status' => 'scheduled',
        'stop_times' => [
            ['stop_uuid' => $scenario['destination']->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Abidjan'],
            ['stop_uuid' => $scenario['middle']->uuid, 'arrival_minutes' => 621, 'departure_minutes' => 631, 'timezone' => 'Africa/Lome'],
            ['stop_uuid' => $scenario['origin']->uuid, 'arrival_minutes' => 1024, 'departure_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
        ],
    ]);

    Fare::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'origin_stop_id' => $scenario['destination']->id,
        'destination_stop_id' => $scenario['origin']->id,
        'service_class_id' => ServiceClass::query()->where('code', 'api-standard')->firstOrFail()->id,
        'base_amount' => 12500,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    // round_trip WITHOUT return_date — should search upcoming return departures
    $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15&trip_type=round_trip')
        ->assertOk()
        ->assertJsonStructure(['outbound', 'inbound'])
        ->assertJsonMissingPath('data')
        ->assertJsonCount(1, 'outbound')
        ->assertJsonCount(1, 'inbound')
        ->assertJsonPath('inbound.0.legs.0.departure_uuid', $returnDeparture->uuid);
});

it('lists lines filtered by ordered city segment', function (): void {
    koraBusApiScenario();

    $this->getJson('/api/v1/bus/lines?from=Cotonou&to=Abidjan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'API-L1')
        ->assertJsonPath('data.0.type', 'nationale')
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.stops.0.id');

    $this->getJson('/api/v1/bus/lines?from=Abidjan&to=Cotonou')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows a line with ordered stops', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson("/api/v1/bus/lines/{$scenario['line']->uuid}")
        ->assertOk()
        ->assertJsonPath('data.uuid', $scenario['line']->uuid)
        ->assertJsonPath('data.stops.0.city', 'Cotonou')
        ->assertJsonPath('data.stops.2.city', 'Abidjan')
        ->assertJsonPath('data.stops.0.physical_stop.name', 'Cotonou Central API')
        ->assertJsonPath('data.stops.0.physical_stop.city', 'Cotonou')
        ->assertJsonPath('data.stops.2.physical_stop.name', 'Abidjan Central API')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.stops.0.id')
        ->assertJsonMissingPath('data.stops.0.physical_stop.id');
});

it('exposes every trip stop with physical stop and per-stop times on itinerary legs', function (): void {
    $scenario = koraBusApiScenario();

    // Departure with explicit stop_times: intermediate stop gets real times.
    $departure = Departure::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $scenario['line']->id,
        'bus_id' => $scenario['bus']->id,
        'departure_date' => '2026-08-15',
        'departure_time' => '09:00',
        'estimated_arrival_at' => CarbonImmutable::parse('2026-08-15 18:45'),
        'status' => 'scheduled',
        'stop_times' => [
            ['stop_uuid' => $scenario['origin']->uuid, 'arrival_minutes' => null, 'departure_minutes' => 0, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $scenario['middle']->uuid, 'arrival_minutes' => 300, 'departure_minutes' => 310, 'timezone' => 'Africa/Lome'],
            ['stop_uuid' => $scenario['destination']->uuid, 'arrival_minutes' => 1024, 'departure_minutes' => null, 'timezone' => 'Africa/Abidjan'],
        ],
    ]);

    $response = $this->getJson('/api/v1/bus/itineraries?from_physical_stop_uuid='.$scenario['origin']->physicalStop->uuid.'&to_physical_stop_uuid='.$scenario['destination']->physicalStop->uuid.'&date=2026-08-15')
        ->assertOk();

    $leg = collect($response->json('data.*.legs'))->flatten(1)->firstWhere('departure_uuid', $departure->uuid);

    expect($leg['trip_stops'])->toHaveCount(3)
        ->and($leg['trip_stops'][0]['physical_stop']['name'])->toBe('Cotonou Central API')
        ->and($leg['trip_stops'][0]['departure_at'])->not->toBeNull()
        ->and($leg['trip_stops'][1]['physical_stop']['name'])->toBe('Lome Central API')
        ->and($leg['trip_stops'][1]['physical_stop']['city'])->toBe('Lome')
        ->and($leg['trip_stops'][1]['arrival_at'])->not->toBeNull()
        ->and($leg['trip_stops'][1]['departure_at'])->not->toBeNull()
        ->and($leg['trip_stops'][1]['timezone'])->toBe('Africa/Lome')
        ->and($leg['trip_stops'][2]['arrival_at'])->not->toBeNull()
        ->and($leg['origin_stop']['physical_stop']['name'])->toBe('Cotonou Central API');
});

it('lists seat availability for an origin destination segment', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}/seats?origin_stop_uuid={$scenario['origin']->uuid}&destination_stop_uuid={$scenario['destination']->uuid}")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['code' => '1A', 'availability_status' => 'blocked'])
        ->assertJsonFragment(['code' => '2A', 'availability_status' => 'occupied'])
        ->assertJsonFragment(['code' => '2B', 'availability_status' => 'available'])
        ->assertJsonMissingPath('data.0.id');
});

it('marks a seat as held (not occupied) with a visible expiry while a temporary hold is active', function (): void {
    $scenario = koraBusApiScenario();

    $heldSeat = Seat::query()->create([
        'bus_id' => $scenario['bus']->id,
        'code' => '3A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $heldReservation = Reservation::query()->create([
        'status' => 'held',
        'total_amount' => 12500,
        'currency' => 'XOF',
        'channel' => 'mobile',
        'expires_at' => CarbonImmutable::parse('2026-08-14 07:10:00'),
    ]);

    ReservationSegment::query()->create([
        'reservation_id' => $heldReservation->id,
        'departure_id' => $scenario['departure']->id,
        'seat_id' => $heldSeat->id,
        'origin_stop_id' => $scenario['origin']->id,
        'destination_stop_id' => $scenario['destination']->id,
        'origin_stop_order' => $scenario['origin']->stop_order,
        'destination_stop_order' => $scenario['destination']->stop_order,
        'status' => 'held',
    ]);

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}/seats?origin_stop_uuid={$scenario['origin']->uuid}&destination_stop_uuid={$scenario['destination']->uuid}")
        ->assertOk()
        ->assertJsonFragment(['code' => '3A', 'availability_status' => 'held'])
        ->assertJsonPath(
            'data.3.held_until',
            fn (?string $heldUntil): bool => $heldUntil !== null
                && CarbonImmutable::parse($heldUntil)->equalTo(CarbonImmutable::parse('2026-08-14 07:10:00')),
        )
        // Un siège occupé (payé/embarqué/confirmé) n'a pas de timer : held_until reste null.
        ->assertJsonFragment(['code' => '2A', 'availability_status' => 'occupied', 'held_until' => null]);
});

it('exposes the canonical seat grid alongside seat availability', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}/seats?origin_stop_uuid={$scenario['origin']->uuid}&destination_stop_uuid={$scenario['destination']->uuid}")
        ->assertOk()
        ->assertJsonPath('composition_uuid', $scenario['composition']->uuid)
        ->assertJsonPath('grid.rows', 2)
        ->assertJsonPath('grid.cols', 2)
        ->assertJsonCount(3, 'grid.cells')
        // La liste `data` reste inchangée : rétrocompatible avec les
        // versions publiées de l'app passager.
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['code' => '2B', 'availability_status' => 'available']);
});

it('returns a null grid when the departure bus has no seat template', function (): void {
    $scenario = koraBusApiScenario();
    $scenario['bus']->update(['composition_id' => null]);

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}/seats?origin_stop_uuid={$scenario['origin']->uuid}&destination_stop_uuid={$scenario['destination']->uuid}")
        ->assertOk()
        ->assertJsonPath('composition_uuid', null)
        ->assertJsonPath('grid', null)
        ->assertJsonCount(3, 'data');
});

it('returns 404 for unknown public uuids', function (): void {
    $this->getJson('/api/v1/bus/lines/'.Str::uuid())->assertNotFound();
});

it('validates seat filters', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}/seats")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin_stop_uuid', 'destination_stop_uuid']);
});

it('does not expose numeric ids anywhere in public bus payloads', function (string $uri): void {
    $scenario = koraBusApiScenario();

    $uri = strtr($uri, [
        '{line}' => $scenario['line']->uuid,
        '{departure}' => $scenario['departure']->uuid,
        '{origin}' => $scenario['origin']->uuid,
        '{destination}' => $scenario['destination']->uuid,
        '{originPhysical}' => $scenario['origin']->physicalStop->uuid,
        '{destinationPhysical}' => $scenario['destination']->physicalStop->uuid,
    ]);

    $payload = $this->getJson($uri)->assertOk()->json();

    expectPayloadToHideIds($payload);
})->with([
    'cities' => '/api/v1/bus/cities',
    'lines' => '/api/v1/bus/lines?from=Cotonou&to=Abidjan',
    'line' => '/api/v1/bus/lines/{line}',
    'seats' => '/api/v1/bus/departures/{departure}/seats?origin_stop_uuid={origin}&destination_stop_uuid={destination}',
    'itineraries' => '/api/v1/bus/itineraries?from_physical_stop_uuid={originPhysical}&to_physical_stop_uuid={destinationPhysical}&date=2026-08-15',
]);

/**
 * @return array<string, mixed>
 */
function koraBusApiScenario(): array
{
    $line = Line::query()->firstOrCreate(
        ['code' => 'API-L1'],
        [
            'name' => 'Cotonou - Abidjan API',
            'countries_crossed' => ['BJ', 'TG', 'CI'],
            'distance_km' => 1020,
            'estimated_duration_minutes' => 1260,
            'status' => 'active',
        ],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Cotonou Central API'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou API', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $middlePhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Lome Central API'],
        ['city' => 'Lome', 'country' => 'TG', 'status' => 'active'],
    );
    $middle = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $middlePhysical->id, 'name' => 'Lome API', 'city' => 'Lome', 'country' => 'TG', 'km_from_origin' => 170, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'Abidjan Central API'],
        ['city' => 'Abidjan', 'country' => 'CI', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 3],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'Abidjan API', 'city' => 'Abidjan', 'country' => 'CI', 'km_from_origin' => 1020, 'status' => 'active'],
    );

    $composition = BusComposition::query()->firstOrCreate(
        ['code' => 'API-COMP-1'],
        [
            'name' => 'Coach API 3 places',
            'seat_count' => 3,
            'layout' => [
                ['type' => 'row', 'codes' => ['1A']],
                ['type' => 'row', 'codes' => ['2A', '2B']],
            ],
            'status' => 'active',
        ],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'API-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach API', 'seat_count' => 3, 'composition_id' => $composition->id, 'status' => 'active'],
    );

    $wifi = BusAmenity::query()->firstOrCreate(
        ['code' => 'wifi'],
        ['label' => 'Wi-Fi à bord', 'icon' => 'wifi', 'status' => 'active'],
    );
    $archivedAmenity = BusAmenity::query()->firstOrCreate(
        ['code' => 'legacy-tv'],
        ['label' => 'Écran TV (retiré)', 'icon' => 'tv', 'status' => 'archived'],
    );
    $bus->amenities()->sync([$wifi->id, $archivedAmenity->id]);

    $blockedSeat = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '1A'],
        ['type' => 'crew', 'sellable' => false, 'status' => 'blocked'],
    );
    $occupiedSeat = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2A'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );
    $availableSeat = Seat::query()->firstOrCreate(
        ['bus_id' => $bus->id, 'code' => '2B'],
        ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
    );

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-15', 'departure_time' => '07:30'],
        ['estimated_arrival_at' => CarbonImmutable::parse('2026-08-15 17:15'), 'status' => 'scheduled'],
    );

    $serviceClass = ServiceClass::query()->firstOrCreate(
        ['code' => 'api-standard'],
        ['name' => 'Standard API', 'description' => 'Sièges inclinables, climatisation, prise USB.', 'status' => 'active'],
    );

    Fare::query()->firstOrCreate(
        ['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id],
        ['service_class_id' => $serviceClass->id, 'base_amount' => 12500, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active'],
    );

    $reservation = Reservation::query()->create([
        'status' => 'confirmed',
        'total_amount' => 12500,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);

    ReservationSegment::query()->firstOrCreate(
        ['reservation_id' => $reservation->id, 'seat_id' => $occupiedSeat->id],
        [
            'departure_id' => $departure->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $middle->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $middle->stop_order,
            'status' => 'confirmed',
        ],
    );

    return compact('line', 'origin', 'middle', 'destination', 'composition', 'bus', 'blockedSeat', 'occupiedSeat', 'availableSeat', 'departure');
}

/**
 * @param  array<string, mixed>  $payload
 */
function expectPayloadToHideIds(array $payload): void
{
    $flattened = Arr::dot($payload);

    foreach (array_keys($flattened) as $key) {
        expect($key)->not->toEndWith('.id')
            ->and($key)->not->toBe('id');
    }
}
