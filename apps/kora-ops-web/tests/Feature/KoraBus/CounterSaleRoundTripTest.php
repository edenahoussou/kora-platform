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

it('renders the sale page with trip type and return date filters', function (): void {
    $scenario = roundTripCounterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->get(route('sale', [
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->where('search.tripType', 'round_trip')
            ->where('search.returnDate', '2026-08-25')
            ->has('inboundItineraries'));
});

it('builds a unified round-trip itinerary quote with outbound and inbound legs', function (): void {
    $scenario = roundTripCounterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'passenger_count' => 1,
            'outbound_legs' => [[
                'departure_uuid' => $scenario['outboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundDestination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundDestination']->uuid,
            ]],
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    expect($quote->passenger_count)->toBe(1);

    $legs = ItineraryQuoteLeg::query()->where('itinerary_quote_id', $quote->id)->orderBy('leg_order')->get();
    expect($legs)->toHaveCount(2)
        ->and($legs[0]->direction)->toBe('outbound')
        ->and($legs[1]->direction)->toBe('inbound')
        ->and($legs[0]->leg_order)->toBe(1)
        ->and($legs[1]->leg_order)->toBe(2);
});

it('creates one reservation with direction-aware segments for a round trip', function (): void {
    $scenario = roundTripCounterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'passenger_count' => 1,
            'outbound_legs' => [[
                'departure_uuid' => $scenario['outboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundDestination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundDestination']->uuid,
            ]],
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => 'round-trip-hold-1',
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['outboundSeat']->uuid],
                    ['leg_order' => 2, 'direction' => 'inbound', 'seat_uuid' => $scenario['inboundSeat']->uuid],
                ],
            ]],
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->segments)->toHaveCount(2)
        ->and($reservation->segments->firstWhere('leg_order', 1)->direction)->toBe('outbound')
        ->and($reservation->segments->firstWhere('leg_order', 2)->direction)->toBe('inbound')
        ->and($reservation->channel)->toBe(ReservationChannel::Counter);
});

it('rejects a round-trip quote where the inbound does not return to the origin', function (): void {
    $scenario = roundTripCounterSaleScenario();

    // Build a wrong inbound destination on the inbound line that does NOT
    // map back to the outbound origin physical stop.
    $wrongPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Lome Physical',
        'city' => 'Lome',
        'country' => 'TG',
        'status' => 'active',
    ]);
    $wrongInboundDestination = Stop::query()->create([
        'line_id' => $scenario['inboundLine']->id,
        'physical_stop_id' => $wrongPhysical->id,
        'name' => 'Lome Arrivée',
        'city' => 'Lome',
        'country' => 'TG',
        'stop_order' => 3,
        'km_from_origin' => 1500,
        'status' => 'active',
    ]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'passenger_count' => 1,
            'outbound_legs' => [[
                'departure_uuid' => $scenario['outboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundDestination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundOrigin']->uuid,
                'destination_stop_uuid' => $wrongInboundDestination->uuid,
            ]],
        ])
        ->assertSessionHasErrors(['inbound_legs']);
});

it('requires a seat assignment per passenger per leg on a round-trip hold', function (): void {
    $scenario = roundTripCounterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'passenger_count' => 1,
            'outbound_legs' => [[
                'departure_uuid' => $scenario['outboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundDestination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundDestination']->uuid,
            ]],
        ])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'trip_type' => 'round_trip',
            'return_date' => '2026-08-25',
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => 'round-trip-missing-seat',
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [
                    ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['outboundSeat']->uuid],
                ],
            ]],
        ])
        ->assertSessionHasErrors(['passengers.0.seat_assignments']);
});

/**
 * @return array<string, mixed>
 */
function roundTripCounterSaleScenario(): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $outboundLine = Line::query()->create([
        'code' => 'RT-OUT',
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

    $outboundOrigin = Stop::query()->create([
        'line_id' => $outboundLine->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Départ',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $outboundDestination = Stop::query()->create([
        'line_id' => $outboundLine->id,
        'physical_stop_id' => $abidjanPhysical->id,
        'name' => 'Abidjan Arrivée',
        'city' => 'Abidjan',
        'country' => 'CI',
        'stop_order' => 2,
        'km_from_origin' => 1020,
        'status' => 'active',
    ]);

    $inboundLine = Line::query()->create([
        'code' => 'RT-IN',
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

    $outboundBus = Bus::query()->create([
        'registration_number' => 'RT-OUT-BUS',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $inboundBus = Bus::query()->create([
        'registration_number' => 'RT-IN-BUS',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);

    $outboundSeat = Seat::query()->create([
        'bus_id' => $outboundBus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);
    $inboundSeat = Seat::query()->create([
        'bus_id' => $inboundBus->id,
        'code' => '1A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $outboundDeparture = Departure::query()->create([
        'line_id' => $outboundLine->id,
        'bus_id' => $outboundBus->id,
        'departure_date' => '2026-08-20',
        'departure_time' => '08:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-20 20:00'),
        'status' => 'scheduled',
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
        'line_id' => $outboundLine->id,
        'origin_stop_id' => $outboundOrigin->id,
        'destination_stop_id' => $outboundDestination->id,
        'base_amount' => 10000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
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

    return compact(
        'agent',
        'outboundLine',
        'inboundLine',
        'outboundOrigin',
        'outboundDestination',
        'inboundOrigin',
        'inboundDestination',
        'outboundBus',
        'inboundBus',
        'outboundSeat',
        'inboundSeat',
        'outboundDeparture',
        'inboundDeparture',
    );
}
