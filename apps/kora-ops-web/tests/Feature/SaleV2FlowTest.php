<?php

use App\Models\User;
use App\Modules\Booking\Models\JourneyQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use Carbon\Carbon;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-19 07:00'));
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/** @return array<string, mixed> */
function saleV2Scenario(): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');
    $line = Line::query()->create(['code' => 'V2-L1', 'name' => 'V2 Cotonou Abidjan', 'countries_crossed' => ['BJ', 'CI'], 'status' => 'active']);
    $originPhysical = PhysicalStop::query()->create(['name' => 'V2 Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active']);
    $destinationPhysical = PhysicalStop::query()->create(['name' => 'V2 Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'status' => 'active']);
    $origin = Stop::query()->create(['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'status' => 'active']);
    $destination = Stop::query()->create(['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'stop_order' => 2, 'status' => 'active']);
    $bus = Bus::query()->create(['registration_number' => 'V2-BUS-1', 'brand' => 'KORA', 'model' => 'Test', 'seat_count' => 1, 'status' => 'active']);
    $seat = Seat::query()->create(['bus_id' => $bus->id, 'code' => '1A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']);
    $departure = Departure::query()->create(['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-20', 'departure_time' => '08:00', 'scheduled_at' => Carbon::parse('2026-08-20 08:00'), 'estimated_arrival_at' => Carbon::parse('2026-08-20 12:00'), 'status' => 'scheduled']);
    Fare::query()->create(['line_id' => $line->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id, 'base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active']);

    return compact('agent', 'origin', 'destination', 'originPhysical', 'destinationPhysical', 'seat', 'departure');
}

it('renders the isolated sale v2 search contract with physical stop identifiers', function (): void {
    $scenario = saleV2Scenario();

    $this->actingAs($scenario['agent'])
        ->get(route('sale-v2', [
            'trip_type' => 'one_way',
            'origin_physical_stop_uuid' => $scenario['originPhysical']->uuid,
            'destination_physical_stop_uuid' => $scenario['destinationPhysical']->uuid,
            'departure_date' => '2026-08-20',
            'passenger_count' => 1,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale-v2')
            ->where('search.tripType', 'one_way')
            ->where('search.originPhysicalStopUuid', $scenario['originPhysical']->uuid)
            ->where('journeys.outbound.0.direction', 'outbound')
            ->missing('journeys.outbound.0.id'));
});

it('creates a global one-way journey quote and an atomic direction-aware hold', function (): void {
    $scenario = saleV2Scenario();
    $context = [
        'trip_type' => 'one_way',
        'date' => '2026-08-20',
        'departure_date' => '2026-08-20',
        'origin_physical_stop_uuid' => $scenario['originPhysical']->uuid,
        'destination_physical_stop_uuid' => $scenario['destinationPhysical']->uuid,
        'passenger_count' => 1,
        'outbound_legs' => [[
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ];

    $this->actingAs($scenario['agent'])
        ->post(route('sale-v2.quote'), $context)
        ->assertRedirect()
        ->assertSessionDoesntHaveErrors();

    $quote = JourneyQuote::query()->latest('id')->firstOrFail();
    expect($quote->trip_type)->toBe('one_way')
        ->and($quote->legs)->toHaveCount(1);

    $this->actingAs($scenario['agent'])
        ->post(route('sale-v2.hold'), [
            'journey_quote_uuid' => $quote->uuid,
            'date' => '2026-08-20',
            'passengers' => [[
                'passenger_index' => 0,
                'seat_assignments' => [[
                    'direction' => 'outbound',
                    'leg_order' => 1,
                    'seat_uuid' => $scenario['seat']->uuid,
                ]],
            ]],
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->journey_quote_id)->toBe($quote->id)
        ->and($reservation->segments)->toHaveCount(1)
        ->and($reservation->segments->first()->direction)->toBe('outbound');
});
