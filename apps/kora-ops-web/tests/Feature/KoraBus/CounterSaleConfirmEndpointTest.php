<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\ItineraryQuoteLeg;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\Promotion;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('creates a quote with the final passenger count and holds seats in a single confirm call', function (): void {
    $scenario = confirmEndpointScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'passenger_count' => 1,
            'idempotency_key' => 'confirm-test-1',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seat']->uuid],
                ],
            ]],
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    expect($quote->passenger_count)->toBe(1);

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->segments)->toHaveCount(1)
        ->and($reservation->channel)->toBe(ReservationChannel::Counter);
});

it('creates a quote with the correct passenger count for group bookings', function (): void {
    $scenario = confirmEndpointScenario();

    // Build a bus with enough seats for 5 passengers.
    $seats = collect();
    for ($i = 1; $i <= 5; $i++) {
        $seats->push(Seat::query()->create([
            'bus_id' => $scenario['bus']->id,
            'code' => "1{$i}A",
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]));
    }

    $passengers = [];
    for ($i = 0; $i < 5; $i++) {
        $passengers[] = [
            'passenger_index' => $i,
            'seat_assignments' => [
                ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $seats[$i]->uuid],
            ],
        ];
    }

    $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'passenger_count' => 5,
            'idempotency_key' => 'confirm-group-1',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
            'passengers' => $passengers,
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    expect($quote->passenger_count)->toBe(5)
        ->and($quote->total_amount)->toBeGreaterThan(0);

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->segments)->toHaveCount(5);
});

it('creates a round-trip quote and holds seats for both directions', function (): void {
    $scenario = confirmEndpointScenario(true);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'passenger_count' => 1,
            'idempotency_key' => 'confirm-rt-1',
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
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seat']->uuid],
                    ['leg_order' => 2, 'direction' => 'inbound', 'seat_uuid' => $scenario['inboundSeat']->uuid],
                ],
            ]],
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    expect($quote->passenger_count)->toBe(1);

    $legs = ItineraryQuoteLeg::query()
        ->where('itinerary_quote_id', $quote->id)
        ->orderBy('leg_order')
        ->get();
    expect($legs)->toHaveCount(2)
        ->and($legs[0]->direction)->toBe('outbound')
        ->and($legs[1]->direction)->toBe('inbound');

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->segments)->toHaveCount(2);
});

it('validates required fields', function (): void {
    $scenario = confirmEndpointScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [])
        ->assertSessionHasErrors(['outbound_legs', 'passenger_count', 'passengers', 'date']);
});

it('redirects business validation errors to the sale page instead of the post-only endpoint', function (): void {
    $scenario = confirmEndpointScenario();

    Promotion::query()->create([
        'name' => 'Promotion brouillon',
        'code' => 'DRAFTPROMO',
        'benefit_type' => 'percentage',
        'benefit_value' => 10,
        'scope_type' => 'network',
        'priority' => 10,
        'starts_at' => now()->subDay(),
        'status' => 'draft',
    ]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'passenger_count' => 1,
            'promo_code' => 'DRAFTPROMO',
            'idempotency_key' => 'confirm-draft-promo',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seat']->uuid],
                ],
            ]],
        ])
        ->assertSessionHasErrors('promo_code')
        ->assertSessionDoesntHaveErrors('seats');

    $response = $this->actingAs($scenario['agent'])
        ->post(route('sale.confirm'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'one_way',
            'passenger_count' => 1,
            'promo_code' => 'DRAFTPROMO',
            'idempotency_key' => 'confirm-draft-promo-2',
            'outbound_legs' => [[
                'departure_uuid' => $scenario['departure']->uuid,
                'origin_stop_uuid' => $scenario['origin']->uuid,
                'destination_stop_uuid' => $scenario['destination']->uuid,
            ]],
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seat']->uuid],
                ],
            ]],
        ]);

    expect($response->headers->get('Location'))->toStartWith(route('sale'));
});

it('redirects stray GET hits on post-only sale endpoints to the sale page', function (): void {
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $this->actingAs($agent)->get('/sale/seats')->assertRedirect(route('sale'));
    $this->actingAs($agent)->get('/sale/confirm')->assertRedirect(route('sale'));
});

function confirmEndpointScenario(bool $roundTrip = false): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $line = Line::query()->create([
        'code' => 'CONFIRM-1',
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
        'registration_number' => 'CONFIRM-BUS',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 10,
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
            'code' => 'CONFIRM-IN',
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
            'registration_number' => 'CONFIRM-IN-BUS',
            'brand' => 'KORA',
            'model' => 'Coach',
            'seat_count' => 10,
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
