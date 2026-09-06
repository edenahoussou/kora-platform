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

it('serializes itineraryQuote.legs with the same camelCase/nested shape as the itinerary search results', function (): void {
    $scenario = roundTripCounterSaleScenario();

    $quoteResponse = $this->actingAs($scenario['agent'])
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

    $this->actingAs($scenario['agent'])
        ->get($quoteResponse->headers->get('Location'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->has('itineraryQuote.legs', 2)
            // The bug: itineraryQuote.legs used to be the raw flat
            // snake_case quote snapshot (leg_order, line_code, ...)
            // instead of the camelCase/nested shape the frontend expects
            // (legOrder, line.code, originStop.city, ...), crashing the
            // seat picker on open.
            ->where('itineraryQuote.legs.0.legOrder', 1)
            ->where('itineraryQuote.legs.0.direction', 'outbound')
            ->where('itineraryQuote.legs.0.line.code', $scenario['outboundLine']->code)
            ->where('itineraryQuote.legs.0.originStop.city', 'Cotonou')
            ->where('itineraryQuote.legs.0.destinationStop.city', 'Abidjan')
            ->has('itineraryQuote.legs.0.departureDate')
            ->has('itineraryQuote.legs.0.departureTime')
            ->has('itineraryQuote.legs.0.departureAt')
            ->has('itineraryQuote.legs.0.arrivalAt')
            ->where('itineraryQuote.legs.1.legOrder', 2)
            ->where('itineraryQuote.legs.1.direction', 'inbound')
            ->where('itineraryQuote.legs.1.line.code', $scenario['inboundLine']->code)
            ->where('itineraryQuote.legs.1.originStop.city', 'Abidjan')
            ->where('itineraryQuote.legs.1.destinationStop.city', 'Cotonou')
            ->missing('itineraryQuote.legs.0.leg_order')
            ->missing('itineraryQuote.legs.0.line_code')
        );
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

it('rejects a round trip where the inbound departs before the outbound ends', function (): void {
    $scenario = multiLegRoundTripScenario();

    // Outbound: Dassa -> Cotonou (06 sept 17:00 -> 07 sept 04:00),
    //           Cotonou -> Porto-Novo (07 sept 06:00 -> 07:27).
    // Inbound:  Porto-Novo -> Cotonou (06 sept 15:00 -> 10:30 next day),
    //           Cotonou -> Dassa (07 sept 13:00 -> 16:39).
    // The inbound starts on 06 sept at 15:00, BEFORE the outbound even
    // departs at 17:00, and well before the outbound ends on 07 sept 07:27.
    // This must be rejected.
    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-09-06',
            'from' => 'Dassa',
            'to' => 'Porto-Novo',
            'trip_type' => 'round_trip',
            'passenger_count' => 1,
            'outbound_legs' => [[
                'departure_uuid' => $scenario['outboundFirstDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundFirstOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundFirstDestination']->uuid,
            ], [
                'departure_uuid' => $scenario['outboundSecondDeparture']->uuid,
                'origin_stop_uuid' => $scenario['outboundSecondOrigin']->uuid,
                'destination_stop_uuid' => $scenario['outboundSecondDestination']->uuid,
            ]],
            'inbound_legs' => [[
                'departure_uuid' => $scenario['inboundFirstDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundFirstOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundFirstDestination']->uuid,
            ], [
                'departure_uuid' => $scenario['inboundSecondDeparture']->uuid,
                'origin_stop_uuid' => $scenario['inboundSecondOrigin']->uuid,
                'destination_stop_uuid' => $scenario['inboundSecondDestination']->uuid,
            ]],
        ])
        ->assertSessionHasErrors(['inbound_legs']);
});

it('holds a round-trip with a reverse-direction outbound departure without violating the stop_order constraint', function (): void {
    $scenario = reverseDirectionRoundTripScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), [
            'date' => '2026-09-08',
            'from' => 'Dassa',
            'to' => 'Cotonou',
            'trip_type' => 'round_trip',
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
            'date' => '2026-09-08',
            'from' => 'Dassa',
            'to' => 'Cotonou',
            'trip_type' => 'round_trip',
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => 'reverse-direction-hold-1',
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
        ->and($reservation->segments->firstWhere('leg_order', 1)->origin_stop_order)->toBeLessThan($reservation->segments->firstWhere('leg_order', 1)->destination_stop_order)
        ->and($reservation->segments->firstWhere('leg_order', 2)->direction)->toBe('inbound')
        ->and($reservation->segments->firstWhere('leg_order', 2)->origin_stop_order)->toBeLessThan($reservation->segments->firstWhere('leg_order', 2)->destination_stop_order);
});

/**
 * Build a round-trip scenario where the inbound uses a reverse-direction
 * departure on a 3-stop line. The line's canonical stop_order is
 * Malanville(1) -> Dassa(2) -> Cotonou(3). The reverse bus circulates
 * Cotonou -> Dassa -> Malanville, so a passenger boarding at Cotonou
 * (canonical=3) and alighting at Dassa (canonical=2) has descending
 * canonical stop_order — which would violate the PostgreSQL CHECK
 * (origin_stop_order < destination_stop_order) constraint. The fix
 * stores circulation indices (Cotonou=0, Dassa=1) instead.
 *
 * @return array<string, mixed>
 */
function reverseDirectionRoundTripScenario(): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $malanvillePhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Malanville Physical',
        'city' => 'Malanville',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $dassaPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Dassa Physical',
        'city' => 'Dassa',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $cotonouPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    // Single line with 3 stops: Malanville(1) -> Dassa(2) -> Cotonou(3)
    $line = Line::query()->create([
        'code' => 'REV-LINE',
        'name' => 'Malanville - Cotonou',
        'countries_crossed' => ['BJ'],
        'distance_km' => 600,
        'estimated_duration_minutes' => 480,
        'status' => 'active',
    ]);

    $malanvilleStop = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $malanvillePhysical->id,
        'name' => 'Malanville',
        'city' => 'Malanville',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $dassaStop = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $dassaPhysical->id,
        'name' => 'Dassa',
        'city' => 'Dassa',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 300,
        'status' => 'active',
    ]);
    $cotonouStop = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 3,
        'km_from_origin' => 600,
        'status' => 'active',
    ]);

    $outboundBus = Bus::query()->create([
        'registration_number' => 'REV-BUS-OUT',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $inboundBus = Bus::query()->create([
        'registration_number' => 'REV-BUS-IN',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);

    Seat::query()->create(['bus_id' => $outboundBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $inboundBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    // Outbound: forward departure Dassa -> Cotonou on 2026-09-08.
    // stop_times: Malanville(0) -> Dassa(180) -> Cotonou(360).
    // Passenger boards Dassa (circulation index 1), alights Cotonou (index 2).
    // Canonical: Dassa=2, Cotonou=3 — ascending — OK.
    $outboundDeparture = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $outboundBus->id,
        'departure_date' => '2026-09-08',
        'departure_time' => '06:00',
        'scheduled_at' => Carbon::parse('2026-09-08 06:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $malanvilleStop->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $dassaStop->uuid, 'departure_minutes' => 180, 'arrival_minutes' => 180, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $cotonouStop->uuid, 'departure_minutes' => null, 'arrival_minutes' => 360, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-08 12:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    // Inbound: REVERSE departure Cotonou -> Dassa on 2026-09-09.
    // stop_times: Cotonou(0) -> Dassa(180) -> Malanville(360).
    // Passenger boards Cotonou (circulation index 0), alights Dassa (index 1).
    // Canonical: Cotonou=3, Dassa=2 — DESCENDING — would VIOLATE check.
    // Circulation: Cotonou=0, Dassa=1 — ASCENDING — OK.
    $inboundDeparture = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $inboundBus->id,
        'departure_date' => '2026-09-09',
        'departure_time' => '06:00',
        'scheduled_at' => Carbon::parse('2026-09-09 06:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $cotonouStop->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $dassaStop->uuid, 'departure_minutes' => 180, 'arrival_minutes' => 180, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $malanvilleStop->uuid, 'departure_minutes' => null, 'arrival_minutes' => 360, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-09 12:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::query()->create([
        'line_id' => $line->id,
        'origin_stop_id' => $dassaStop->id,
        'destination_stop_id' => $cotonouStop->id,
        'base_amount' => 3000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);
    Fare::query()->create([
        'line_id' => $line->id,
        'origin_stop_id' => $cotonouStop->id,
        'destination_stop_id' => $dassaStop->id,
        'base_amount' => 3000,
        'currency' => 'XOF',
        'effective_from' => '2026-01-01',
        'status' => 'active',
    ]);

    return [
        'agent' => $agent,
        'outboundDeparture' => $outboundDeparture,
        'inboundDeparture' => $inboundDeparture,
        'outboundOrigin' => $dassaStop,
        'outboundDestination' => $cotonouStop,
        'inboundOrigin' => $cotonouStop,
        'inboundDestination' => $dassaStop,
        'outboundSeat' => Seat::query()->where('bus_id', $outboundBus->id)->first(),
        'inboundSeat' => Seat::query()->where('bus_id', $inboundBus->id)->first(),
    ];
}

/**
 * @return array<string, mixed>
 */
function multiLegRoundTripScenario(): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $dassaPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Dassa Physical',
        'city' => 'Dassa',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $cotonouPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $portoNovoPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Porto-Novo Physical',
        'city' => 'Porto-Novo',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    // Outbound line 1: Dassa -> Cotonou
    $outboundLine1 = Line::query()->create([
        'code' => 'ML-OUT-1',
        'name' => 'Dassa - Cotonou',
        'countries_crossed' => ['BJ'],
        'distance_km' => 200,
        'estimated_duration_minutes' => 660,
        'status' => 'active',
    ]);
    // Outbound line 2: Cotonou -> Porto-Novo
    $outboundLine2 = Line::query()->create([
        'code' => 'ML-OUT-2',
        'name' => 'Cotonou - Porto-Novo',
        'countries_crossed' => ['BJ'],
        'distance_km' => 100,
        'estimated_duration_minutes' => 87,
        'status' => 'active',
    ]);
    // Inbound line 1: Porto-Novo -> Cotonou
    $inboundLine1 = Line::query()->create([
        'code' => 'ML-IN-1',
        'name' => 'Porto-Novo - Cotonou',
        'countries_crossed' => ['BJ'],
        'distance_km' => 100,
        'estimated_duration_minutes' => 1170,
        'status' => 'active',
    ]);
    // Inbound line 2: Cotonou -> Dassa
    $inboundLine2 = Line::query()->create([
        'code' => 'ML-IN-2',
        'name' => 'Cotonou - Dassa',
        'countries_crossed' => ['BJ'],
        'distance_km' => 200,
        'estimated_duration_minutes' => 219,
        'status' => 'active',
    ]);

    $outboundFirstOrigin = Stop::query()->create([
        'line_id' => $outboundLine1->id,
        'physical_stop_id' => $dassaPhysical->id,
        'name' => 'Dassa Départ',
        'city' => 'Dassa',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $outboundFirstDestination = Stop::query()->create([
        'line_id' => $outboundLine1->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Arrivée',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 200,
        'status' => 'active',
    ]);
    $outboundSecondOrigin = Stop::query()->create([
        'line_id' => $outboundLine2->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Départ',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $outboundSecondDestination = Stop::query()->create([
        'line_id' => $outboundLine2->id,
        'physical_stop_id' => $portoNovoPhysical->id,
        'name' => 'Porto-Novo Arrivée',
        'city' => 'Porto-Novo',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 100,
        'status' => 'active',
    ]);
    $inboundFirstOrigin = Stop::query()->create([
        'line_id' => $inboundLine1->id,
        'physical_stop_id' => $portoNovoPhysical->id,
        'name' => 'Porto-Novo Départ',
        'city' => 'Porto-Novo',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $inboundFirstDestination = Stop::query()->create([
        'line_id' => $inboundLine1->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Arrivée',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 100,
        'status' => 'active',
    ]);
    $inboundSecondOrigin = Stop::query()->create([
        'line_id' => $inboundLine2->id,
        'physical_stop_id' => $cotonouPhysical->id,
        'name' => 'Cotonou Départ',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $inboundSecondDestination = Stop::query()->create([
        'line_id' => $inboundLine2->id,
        'physical_stop_id' => $dassaPhysical->id,
        'name' => 'Dassa Arrivée',
        'city' => 'Dassa',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 200,
        'status' => 'active',
    ]);

    $outboundFirstBus = Bus::query()->create([
        'registration_number' => 'ML-OUT-BUS-1',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $outboundSecondBus = Bus::query()->create([
        'registration_number' => 'ML-OUT-BUS-2',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $inboundFirstBus = Bus::query()->create([
        'registration_number' => 'ML-IN-BUS-1',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);
    $inboundSecondBus = Bus::query()->create([
        'registration_number' => 'ML-IN-BUS-2',
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);

    Seat::query()->create(['bus_id' => $outboundFirstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $outboundSecondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $inboundFirstBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    Seat::query()->create(['bus_id' => $inboundSecondBus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);

    // Outbound leg 1: 06 sept 17:00 -> 07 sept 04:00 (660 min = 11h)
    $outboundFirstDeparture = Departure::query()->create([
        'line_id' => $outboundLine1->id,
        'bus_id' => $outboundFirstBus->id,
        'departure_date' => '2026-09-06',
        'departure_time' => '17:00',
        'scheduled_at' => Carbon::parse('2026-09-06 17:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $outboundFirstOrigin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $outboundFirstDestination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 660, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-07 04:00', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);
    // Outbound leg 2: 07 sept 06:00 -> 07:27 (87 min)
    $outboundSecondDeparture = Departure::query()->create([
        'line_id' => $outboundLine2->id,
        'bus_id' => $outboundSecondBus->id,
        'departure_date' => '2026-09-07',
        'departure_time' => '06:00',
        'scheduled_at' => Carbon::parse('2026-09-07 06:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $outboundSecondOrigin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $outboundSecondDestination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 87, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-07 07:27', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);
    // Inbound leg 1: 06 sept 15:00 -> 07 sept 10:30 (1170 min = 19h30)
    // This departs BEFORE the outbound ends — must be rejected.
    $inboundFirstDeparture = Departure::query()->create([
        'line_id' => $inboundLine1->id,
        'bus_id' => $inboundFirstBus->id,
        'departure_date' => '2026-09-06',
        'departure_time' => '15:00',
        'scheduled_at' => Carbon::parse('2026-09-06 15:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $inboundFirstOrigin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $inboundFirstDestination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 1170, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-07 10:30', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);
    // Inbound leg 2: 07 sept 13:00 -> 16:39 (219 min)
    $inboundSecondDeparture = Departure::query()->create([
        'line_id' => $inboundLine2->id,
        'bus_id' => $inboundSecondBus->id,
        'departure_date' => '2026-09-07',
        'departure_time' => '13:00',
        'scheduled_at' => Carbon::parse('2026-09-07 13:00', 'Africa/Porto-Novo'),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $inboundSecondOrigin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $inboundSecondDestination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 219, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => Carbon::parse('2026-09-07 16:39', 'Africa/Porto-Novo'),
        'status' => 'scheduled',
    ]);

    Fare::query()->create(['line_id' => $outboundLine1->id, 'origin_stop_id' => $outboundFirstOrigin->id, 'destination_stop_id' => $outboundFirstDestination->id, 'base_amount' => 4400, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::query()->create(['line_id' => $outboundLine2->id, 'origin_stop_id' => $outboundSecondOrigin->id, 'destination_stop_id' => $outboundSecondDestination->id, 'base_amount' => 1100, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::query()->create(['line_id' => $inboundLine1->id, 'origin_stop_id' => $inboundFirstOrigin->id, 'destination_stop_id' => $inboundFirstDestination->id, 'base_amount' => 1100, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);
    Fare::query()->create(['line_id' => $inboundLine2->id, 'origin_stop_id' => $inboundSecondOrigin->id, 'destination_stop_id' => $inboundSecondDestination->id, 'base_amount' => 4400, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact(
        'agent',
        'outboundFirstDeparture',
        'outboundSecondDeparture',
        'inboundFirstDeparture',
        'inboundSecondDeparture',
        'outboundFirstOrigin',
        'outboundFirstDestination',
        'outboundSecondOrigin',
        'outboundSecondDestination',
        'inboundFirstOrigin',
        'inboundFirstDestination',
        'inboundSecondOrigin',
        'inboundSecondDestination',
    );
}

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
