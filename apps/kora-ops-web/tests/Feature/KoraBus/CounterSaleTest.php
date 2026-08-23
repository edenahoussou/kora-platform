<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('renders upcoming scheduled departures by default', function (): void {
    $scenario = counterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->get(route('sale'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->where('search.date', null)
            ->where('departures.0.uuid', $scenario['departure']->uuid)
        );
});

it('renders the counter sale page with real departure and seat props', function (): void {
    $scenario = counterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->get(route('sale', [
            'date' => '2026-08-20',
            'from' => 'Cotonou',
            'to' => 'Abidjan',
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->where('permissions.canCreate', true)
            ->where('departures.0.uuid', $scenario['departure']->uuid)
            ->where('selectedDeparture.uuid', $scenario['departure']->uuid)
            ->where('seats.1.code', '2A')
            ->missing('departures.0.id')
            ->missing('selectedDeparture.id')
            ->missing('seats.0.id')
        );
});

it('creates a counter quote and blocks seats through the web flow', function (): void {
    $scenario = counterSaleScenario();
    $context = [
        'date' => '2026-08-20',
        'from' => 'Cotonou',
        'to' => 'Abidjan',
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ];

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1])
        ->assertRedirect();

    $quote = PriceQuote::query()->latest('id')->firstOrFail();

    $response = $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $quote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ]);

    $response->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();

    expect($reservation->channel)->toBe(ReservationChannel::Counter);
    $this->assertDatabaseHas('reservation_segments', [
        'reservation_id' => $reservation->id,
        'seat_id' => $scenario['seat2A']->id,
        'status' => 'held',
    ]);

    $this->actingAs($scenario['agent'])
        ->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation.uuid', $reservation->uuid)
            ->where('reservation.channel', 'counter')
            ->where('reservation.segments.0.seatCode', '2A')
        );
});

it('denies counter sale actions without the counter sales permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('passenger');

    $this->actingAs($user)
        ->post(route('sale.hold'), [])
        ->assertForbidden();
});

it('returns a validation error when the quote has expired', function (): void {
    $scenario = counterSaleScenario();
    $context = [
        'date' => '2026-08-20',
        'from' => 'Cotonou',
        'to' => 'Abidjan',
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ];

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1]);

    $quote = PriceQuote::query()->latest('id')->firstOrFail();
    $quote->update(['expires_at' => Carbon::now()->subMinute()]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $quote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('price_quote_uuid');
});

it('returns a validation error when a selected seat conflicts with an existing hold', function (): void {
    $scenario = counterSaleScenario();
    $context = [
        'date' => '2026-08-20',
        'from' => 'Cotonou',
        'to' => 'Abidjan',
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ];

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1]);
    $firstQuote = PriceQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $firstQuote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ])
        ->assertRedirect();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1]);
    $secondQuote = PriceQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $secondQuote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('seats');
});

/**
 * @return array<string, mixed>
 */
function counterSaleScenario(): array
{
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    $line = Line::query()->create([
        'code' => 'CS-L1',
        'name' => 'Cotonou - Abidjan Counter Sale',
        'countries_crossed' => ['BJ', 'TG', 'CI'],
        'distance_km' => 1020,
        'estimated_duration_minutes' => 720,
        'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'name' => 'Cotonou Guichet',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'name' => 'Abidjan Guichet',
        'city' => 'Abidjan',
        'country' => 'CI',
        'stop_order' => 2,
        'km_from_origin' => 1020,
        'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'registration_number' => 'CS-BUS-001',
        'brand' => 'KORA',
        'model' => 'Coach Counter',
        'seat_count' => 3,
        'status' => 'active',
    ]);

    $seat1A = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '1A',
        'type' => 'crew',
        'sellable' => false,
        'status' => 'blocked',
    ]);
    $seat2A = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '2A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);
    Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '2B',
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
    PricingRule::query()->create([
        'line_id' => $line->id,
        'type' => PricingRuleType::Increase->value,
        'percentage' => 10,
        'priority' => 10,
        'status' => 'active',
        'conditions' => [],
    ]);

    return compact('agent', 'line', 'origin', 'destination', 'bus', 'seat1A', 'seat2A', 'departure');
}
