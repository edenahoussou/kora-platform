<?php

use App\Models\User;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('returns seat availability without creating an itinerary quote', function (): void {
    $scenario = seatsEndpointScenario();

    $response = $this->actingAs($scenario['agent'])
        ->post(route('sale.seats'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
        ]);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page): Assert => $page
        ->component('sale')
        ->has('itineraryLegSeats')
        ->where('itineraryLegSeats.0.legOrder', 1)
        ->where('itineraryLegSeats.0.direction', 'outbound')
        ->where('itineraryLegSeats.0.departureUuid', $scenario['departure']->uuid));

    expect(ItineraryQuote::count())->toBe(0);
});

it('returns seat availability for round-trip legs with sequential leg ordering', function (): void {
    $scenario = seatsEndpointScenario(true);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.seats'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundDestination']->uuid,
            ]],
        ])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->has('itineraryLegSeats', 2)
            ->where('itineraryLegSeats.0.legOrder', 1)
            ->where('itineraryLegSeats.0.direction', 'outbound')
            ->where('itineraryLegSeats.1.legOrder', 2)
            ->where('itineraryLegSeats.1.direction', 'inbound'));

    expect(ItineraryQuote::count())->toBe(0);
});

it('returns the bus composition grid with seat availability', function (): void {
    $scenario = seatsEndpointScenario();

    $composition = BusComposition::query()->create([
        'code' => 'GAB-SEATS',
        'name' => 'Coach test',
        'seat_count' => 2,
        'layout' => [['type' => 'standard', 'codes' => ['1A', '1B']]],
        'grid' => [
            'rows' => 2,
            'cols' => 3,
            'cells' => [
                ['kind' => 'driver', 'x' => 0, 'y' => 0],
                ['kind' => 'door', 'x' => 2, 'y' => 0],
                ['kind' => 'seat', 'x' => 0, 'y' => 1, 'code' => '1A', 'seatKind' => 'standard'],
                ['kind' => 'aisle', 'x' => 1, 'y' => 1],
                ['kind' => 'seat', 'x' => 2, 'y' => 1, 'code' => '1B', 'seatKind' => 'standard'],
            ],
        ],
        'status' => 'active',
    ]);
    $scenario['bus']->update(['composition_id' => $composition->id]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.seats'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
        ])
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->where('itineraryLegSeats.0.grid.rows', 2)
            ->where('itineraryLegSeats.0.grid.cols', 3)
            ->has('itineraryLegSeats.0.grid.cells', 5));
});

it('validates required outbound legs', function (): void {
    $scenario = seatsEndpointScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.seats'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
        ])
        ->assertSessionHasErrors(['outbound_legs']);
});

function seatsEndpointScenario(bool $roundTrip = false): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $line = Line::query()->create([
        'code' => 'SEATS-1',
        'name' => 'Cotonou - Abidjan',
        'countries_crossed' => ['BJ', 'CI'],
        'distance_km' => 1020,
        'estimated_duration_minutes' => 720,
        'status' => 'active',
    ]);

    $cotonouPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $abidjanPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Abidjan Physical',
        'city' => 'Abidjan',
        'country' => 'CI',
        'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Départ',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $abidjanPhysical->id,
        'name' => 'Abidjan Arrivée',
        'city' => 'Abidjan',
        'country' => 'CI',
        'stop_order' => 2,
        'km_from_origin' => 1020,
        'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'registration_number' => 'SEATS-BUS',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-08-20',
        'departure_time' => '08:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-20 20:00'),
        'status' => 'scheduled',
    ]);

    Fare::query()->create([
        'line_id' => $line->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'base_amount' => 10000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    $result = compact(
        'agent',
        'line',
        'origin',
        'destination',
        'bus',
        'seat',
        'departure',
    );

    if ($roundTrip) {
        $inboundLine = Line::query()->create([
            'code' => 'SEATS-IN',
            'name' => 'Abidjan - Cotonou',
            'countries_crossed' => ['CI', 'BJ'],
            'distance_km' => 1020,
            'estimated_duration_minutes' => 720,
            'status' => 'active',
        ]);

        $inboundOrigin = Stop::query()->create([
            'line_id' => $inboundLine->id,
            'physical_stop_id' => $abidjanPhysical->id,
            'name' => 'Abidjan Départ',
            'city' => 'Abidjan',
            'country' => 'CI',
            'stop_order' => 1,
            'km_from_origin' => 0,
            'status' => 'active',
        ]);
        $inboundDestination = Stop::query()->create([
            'line_id' => $inboundLine->id,
            'physical_stop_id' => $cotonouPhysical->id,
            'name' => 'Cotonou Arrivée',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'stop_order' => 2,
            'km_from_origin' => 1020,
            'status' => 'active',
        ]);

        $inboundBus = Bus::query()->create([
            'registration_number' => 'SEATS-IN-BUS',
            'brand' => 'KORA',
            'model' => 'Coach',
            'seat_count' => 2,
            'status' => 'active',
        ]);
        $inboundSeat = Seat::query()->create([
            'bus_id' => $inboundBus->id,
            'code' => '1A',
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);

        $inboundDeparture = Departure::query()->create([
            'line_id' => $inboundLine->id,
            'bus_id' => $inboundBus->id,
            'departure_date' => '2026-08-25',
            'departure_time' => '08:00',
            'estimated_arrival_at' => Carbon::parse('2026-08-25 20:00'),
            'status' => 'scheduled',
        ]);

        Fare::query()->create([
            'line_id' => $inboundLine->id,
            'origin_stop_id' => $inboundOrigin->id,
            'destination_stop_id' => $inboundDestination->id,
            'base_amount' => 10000,
            'currency' => 'XOF',
            'effective_from' => '2026-01-01',
            'status' => 'active',
        ]);

        $result = array_merge($result, compact(
            'inboundLine',
            'inboundOrigin',
            'inboundDestination',
            'inboundBus',
            'inboundSeat',
            'inboundDeparture',
        ));
    }

    return $result;
}
