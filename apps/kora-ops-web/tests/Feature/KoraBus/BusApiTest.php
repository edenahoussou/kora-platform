<?php

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
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

it('lists active cities derived from stops', function (): void {
    koraBusApiScenario();

    $this->getJson('/api/v1/bus/cities')
        ->assertOk()
        ->assertJsonPath('data.0.city', 'Abidjan')
        ->assertJsonFragment(['city' => 'Cotonou', 'country' => 'BJ'])
        ->assertJsonMissingPath('data.0.id');
});

it('lists lines filtered by ordered city segment', function (): void {
    koraBusApiScenario();

    $this->getJson('/api/v1/bus/lines?from=Cotonou&to=Abidjan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'API-L1')
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
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.stops.0.id');
});

it('lists departures by date and segment with fare preview', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/departures?date=2026-08-15&from=Cotonou&to=Abidjan')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $scenario['departure']->uuid)
        ->assertJsonPath('data.0.available_seats_count', 1)
        ->assertJsonPath('data.0.fare_preview.final_amount', 12500)
        ->assertJsonPath('data.0.fare_preview.is_quote', false)
        ->assertJsonMissingPath('data.0.id')
        ->assertJsonMissingPath('data.0.line.id')
        ->assertJsonMissingPath('data.0.bus.id');
});

it('shows a departure with bus line segment and fare preview', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson("/api/v1/bus/departures/{$scenario['departure']->uuid}?from=Cotonou&to=Abidjan")
        ->assertOk()
        ->assertJsonPath('data.uuid', $scenario['departure']->uuid)
        ->assertJsonPath('data.line.code', 'API-L1')
        ->assertJsonPath('data.origin_stop.uuid', $scenario['origin']->uuid)
        ->assertJsonPath('data.destination_stop.uuid', $scenario['destination']->uuid)
        ->assertJsonPath('data.fare_preview.currency', 'XOF')
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.origin_stop.id');
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

it('returns 404 for unknown public uuids', function (): void {
    $this->getJson('/api/v1/bus/lines/'.Str::uuid())->assertNotFound();
    $this->getJson('/api/v1/bus/departures/'.Str::uuid())->assertNotFound();
});

it('validates departure and seat filters', function (): void {
    $scenario = koraBusApiScenario();

    $this->getJson('/api/v1/bus/departures?date=15-08-2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);

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
    ]);

    $payload = $this->getJson($uri)->assertOk()->json();

    expectPayloadToHideIds($payload);
})->with([
    'cities' => '/api/v1/bus/cities',
    'lines' => '/api/v1/bus/lines?from=Cotonou&to=Abidjan',
    'line' => '/api/v1/bus/lines/{line}',
    'departures' => '/api/v1/bus/departures?date=2026-08-15&from=Cotonou&to=Abidjan',
    'departure' => '/api/v1/bus/departures/{departure}?from=Cotonou&to=Abidjan',
    'seats' => '/api/v1/bus/departures/{departure}/seats?origin_stop_uuid={origin}&destination_stop_uuid={destination}',
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

    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['name' => 'Cotonou API', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $middle = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['name' => 'Lome API', 'city' => 'Lome', 'country' => 'TG', 'km_from_origin' => 170, 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 3],
        ['name' => 'Abidjan API', 'city' => 'Abidjan', 'country' => 'CI', 'km_from_origin' => 1020, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'API-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach API', 'seat_count' => 3, 'status' => 'active'],
    );

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

    Fare::query()->firstOrCreate(
        ['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id],
        ['base_amount' => 12500, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active'],
    );

    $reservation = Reservation::query()->firstOrCreate(
        ['departure_id' => $departure->id, 'status' => 'confirmed'],
        ['total_amount' => 12500, 'currency' => 'XOF', 'channel' => 'online'],
    );

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

    return compact('line', 'origin', 'middle', 'destination', 'bus', 'blockedSeat', 'occupiedSeat', 'availableSeat', 'departure');
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
