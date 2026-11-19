<?php

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\Settings\Settings\OperationsSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    // Freeze time before every scheduled departure so all legs remain
    // sellable (sale closes 30 minutes before departure).
    Carbon::setTestNow('2026-09-04 08:00:00');

    app(OperationsSettings::class)->fill([
        'maxWaitHours' => 24,
        'defaultSearchDays' => 7,
    ])->save();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('finds a connection whose second leg departs on date+2 within the max-wait window', function (): void {
    $scenario = longTripConnectionScenario();

    // Leg 2 departs the hub on 2026-09-07 — within maxWaitHours (24h) but
    // on a service day beyond the old date+1 loading window.
    $response = $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_physical_stop_uuid' => $scenario['originPhysical']->uuid,
        'to_physical_stop_uuid' => $scenario['destinationPhysical']->uuid,
        'date' => '2026-09-05',
        'passenger_count' => 1,
    ]))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.connections_count'))->toBe(1)
        ->and($response->json('data.0.legs'))->toHaveCount(2)
        ->and($response->json('data.0.legs.1.departure_uuid'))->toBe($scenario['secondLegDeparture']->uuid);
});

it('finds a leg whose boarding falls on the search date even when departure_date is earlier', function (): void {
    $scenario = longTripConnectionScenario();

    // The multi-day departure left its line origin on 2026-09-05 but boards
    // the searched origin on 2026-09-06 — the day the passenger asked for.
    $response = $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_physical_stop_uuid' => $scenario['originPhysical']->uuid,
        'to_physical_stop_uuid' => $scenario['destinationPhysical']->uuid,
        'date' => '2026-09-06',
        'passenger_count' => 1,
    ]))->assertOk();

    expect($response->json('data'))->not->toBeEmpty()
        ->and($response->json('data.0.legs.0.departure_uuid'))->toBe($scenario['firstLegDeparture']->uuid)
        ->and($response->json('data.0.departure_at'))->toBe('2026-09-06T06:00:00+00:00');
});

it('searches by city across every active stop of the city, like the counter', function (): void {
    $scenario = longTripConnectionScenario();
    // A second stop in the origin city, listed first, that no line serves.
    PhysicalStop::create(['name' => 'Annexe', 'city' => 'Origincity', 'country' => 'GH', 'status' => 'active']);

    $byUuid = $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_physical_stop_uuid' => $scenario['originPhysical']->uuid,
        'to_physical_stop_uuid' => $scenario['destinationPhysical']->uuid,
        'date' => '2026-09-05',
    ]))->assertOk();

    $byCity = $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_city' => 'origincity',
        'to_city' => 'Northville',
        'date' => '2026-09-05',
    ]))->assertOk();

    expect($byCity->json('data'))->toHaveCount(1)
        ->and($byCity->json('data'))->toBe($byUuid->json('data'));
});

it('searches a round trip by city', function (): void {
    longTripConnectionScenario();

    $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_city' => 'Origincity',
        'to_city' => 'Northville',
        'date' => '2026-09-05',
        'trip_type' => 'round_trip',
    ]))->assertOk()
        ->assertJsonCount(1, 'outbound')
        ->assertJsonStructure(['outbound', 'inbound']);
});

it('requires either a city or a physical stop on each side', function (): void {
    $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'to_city' => 'Northville',
        'date' => '2026-09-05',
    ]))->assertUnprocessable()->assertJsonValidationErrors(['from_city', 'from_physical_stop_uuid']);
});

it('returns no itinerary for an unknown city', function (): void {
    longTripConnectionScenario();

    $this->getJson('/api/v1/bus/itineraries?'.http_build_query([
        'from_city' => 'Atlantis',
        'to_city' => 'Northville',
        'date' => '2026-09-05',
    ]))->assertOk()->assertJsonCount(0, 'data');
});

/** @return array<string, mixed> */
function longTripConnectionScenario(): array
{
    $westPhysical = PhysicalStop::create(['name' => 'West Hub', 'city' => 'Westville', 'country' => 'CI', 'status' => 'active']);
    $originPhysical = PhysicalStop::create(['name' => 'Origin Stop', 'city' => 'Origincity', 'country' => 'GH', 'status' => 'active']);
    $hubPhysical = PhysicalStop::create(['name' => 'Hub Stop', 'city' => 'Hubcity', 'country' => 'BJ', 'status' => 'active']);
    $eastPhysical = PhysicalStop::create(['name' => 'East Stop', 'city' => 'Eastville', 'country' => 'BJ', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::create(['name' => 'North Stop', 'city' => 'Northville', 'country' => 'BJ', 'status' => 'active']);

    $firstLine = Line::create(['code' => 'HRZ-L1', 'name' => 'West-East', 'countries_crossed' => ['CI', 'GH', 'BJ'], 'status' => 'active', 'estimated_duration_minutes' => 1800]);
    $secondLine = Line::create(['code' => 'HRZ-L2', 'name' => 'Hub-North', 'countries_crossed' => ['BJ'], 'status' => 'active', 'estimated_duration_minutes' => 480]);

    $west = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $westPhysical->id, 'name' => 'West Hub', 'city' => 'Westville', 'country' => 'CI', 'stop_order' => 1, 'status' => 'active']);
    $origin = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Origin Stop', 'city' => 'Origincity', 'country' => 'GH', 'stop_order' => 2, 'status' => 'active']);
    $hubArrival = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $hubPhysical->id, 'name' => 'Hub Arrival', 'city' => 'Hubcity', 'country' => 'BJ', 'stop_order' => 3, 'status' => 'active']);
    $east = Stop::create(['line_id' => $firstLine->id, 'physical_stop_id' => $eastPhysical->id, 'name' => 'East Stop', 'city' => 'Eastville', 'country' => 'BJ', 'stop_order' => 4, 'status' => 'active']);
    $hubDeparture = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $hubPhysical->id, 'name' => 'Hub Departure', 'city' => 'Hubcity', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::create(['line_id' => $secondLine->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'North Stop', 'city' => 'Northville', 'country' => 'BJ', 'stop_order' => 2, 'status' => 'active']);

    $firstBus = Bus::create(['registration_number' => 'HRZ-BUS-1', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    $secondBus = Bus::create(['registration_number' => 'HRZ-BUS-2', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);
    Seat::create(['bus_id' => $firstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::create(['bus_id' => $secondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    // Multi-day departure: leaves line origin on 2026-09-05, boards the
    // searched stop on 2026-09-06 and reaches the hub at 11:50 UTC.
    $firstLegDeparture = Departure::create([
        'line_id' => $firstLine->id,
        'bus_id' => $firstBus->id,
        'departure_date' => '2026-09-05',
        'departure_time' => '08:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-05 08:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $west->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 1380, 'arrival_minutes' => 1350, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $hubArrival->uuid, 'departure_minutes' => 1750, 'arrival_minutes' => 1730, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $east->uuid, 'departure_minutes' => null, 'arrival_minutes' => 1800, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-06 14:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    // Same-day departure from the hub: leaves before the first leg arrives,
    // so it can never be the connecting leg.
    Departure::create([
        'line_id' => $secondLine->id,
        'bus_id' => $secondBus->id,
        'departure_date' => '2026-09-06',
        'departure_time' => '07:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-06 07:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $hubDeparture->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 480, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-06 15:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    // Connecting departure on date+2 (within the 24h max-wait window after
    // the first leg's 10:50 UTC arrival, beyond the old date+1 load window).
    $secondLegDeparture = Departure::create([
        'line_id' => $secondLine->id,
        'bus_id' => $secondBus->id,
        'departure_date' => '2026-09-07',
        'departure_time' => '07:00',
        'scheduled_at' => CarbonImmutable::parse('2026-09-07 07:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $hubDeparture->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 480, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => CarbonImmutable::parse('2026-09-07 15:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::create(['line_id' => $firstLine->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $hubArrival->id, 'base_amount' => 6000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::create(['line_id' => $secondLine->id, 'origin_stop_id' => $hubDeparture->id, 'destination_stop_id' => $destination->id, 'base_amount' => 3500, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact(
        'originPhysical',
        'destinationPhysical',
        'firstLegDeparture',
        'secondLegDeparture',
    );
}
