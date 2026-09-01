<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\ServiceClass;
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

it('resumes the latest active counter sale when opening the sale page without context', function (): void {
    $scenario = counterSaleScenario();
    $reservation = createCounterSaleHold($this, $scenario);

    $response = $this->actingAs($scenario['agent'])->get(route('sale'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toContain('reservation_uuid='.$reservation->uuid)
        ->toContain('departure_uuid='.$scenario['departure']->uuid)
        ->toContain('origin_stop_uuid='.$scenario['origin']->uuid)
        ->toContain('destination_stop_uuid='.$scenario['destination']->uuid);
});

it('does not resume a counter sale belonging to another agent', function (): void {
    $scenario = counterSaleScenario();
    createCounterSaleHold($this, $scenario);
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agency_agent');

    $this->actingAs($otherAgent)
        ->get(route('sale'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page->where('reservation', null));
});

it('ignores expired, cancelled, paid and ticketed counter sales during resume', function (): void {
    $scenario = counterSaleScenario();

    foreach ([
        ReservationStatus::Held->value => Carbon::now()->subMinute(),
        ReservationStatus::Cancelled->value => Carbon::now()->addMinutes(10),
        ReservationStatus::Paid->value => Carbon::now()->addMinutes(10),
        ReservationStatus::Ticketed->value => Carbon::now()->addMinutes(10),
    ] as $status => $expiresAt) {
        Reservation::query()->create([
            'user_id' => $scenario['agent']->id,
            'status' => $status,
            'total_amount' => 10000,
            'currency' => 'XOF',
            'channel' => ReservationChannel::Counter,
            'expires_at' => $expiresAt,
        ]);
    }

    $this->actingAs($scenario['agent'])
        ->get(route('sale'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page->where('reservation', null));
});

it('saves a partial counter sale draft and restores it in Inertia props', function (): void {
    $scenario = counterSaleScenario();
    $reservation = createCounterSaleHold($this, $scenario);
    $context = counterSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->patch(route('sale.draft', $reservation), [
            'passengers' => [[
                'first_name' => 'Aïcha',
                'last_name' => '',
                'phone' => '+229 97 24 18 06',
                'email' => '',
                'document_type' => 'national_id',
                'document_number' => '',
                'nationality' => '',
                'birth_date' => '',
                'seat_uuid' => $scenario['seat2A']->uuid,
            ]],
        ])
        ->assertRedirect();

    $draft = $reservation->fresh()->draft_data;
    expect($draft['passengers'][$scenario['seat2A']->uuid]['first_name'])->toBe('Aïcha');
    expect($reservation->fresh()->passengers)->toHaveCount(0);

    $this->actingAs($scenario['agent'])
        ->get(route('sale', $context + ['reservation_uuid' => $reservation->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation.draftPassengers.0.firstName', 'Aïcha')
            ->where('reservation.draftPassengers.0.phone', '+229 97 24 18 06')
            ->where('reservation.passengers', [])
        );
});

it('rejects a draft for an unauthorized or expired reservation', function (): void {
    $scenario = counterSaleScenario();
    $reservation = createCounterSaleHold($this, $scenario);
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agency_agent');
    $payload = [
        'passengers' => [[
            'first_name' => 'Aïcha',
            'seat_uuid' => $scenario['seat2A']->uuid,
        ]],
    ];

    $this->actingAs($otherAgent)
        ->patch(route('sale.draft', $reservation), $payload)
        ->assertForbidden();

    $reservation->update(['expires_at' => Carbon::now()->subMinute()]);
    $this->actingAs($scenario['agent'])
        ->patch(route('sale.draft', $reservation), $payload)
        ->assertSessionHasErrors('reservation');
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
            ->where('departures.0.farePreview.service_class_name', 'KORA Confort')
            ->missing('departures.0.id')
            ->missing('selectedDeparture.id')
            ->missing('seats.0.id')
        );
});

it('renders enriched counter seat statuses without exposing internal ids', function (): void {
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
    $heldQuote = PriceQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $heldQuote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ]);

    $heldReservation = Reservation::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1]);
    $paidQuote = PriceQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $paidQuote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2B']->uuid]],
        ]);

    $paidReservation = Reservation::query()->latest('id')->firstOrFail();
    $paidReservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);

    $this->actingAs($scenario['agent'])
        ->get(route('sale', $context))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('seats.0.availabilityStatus', 'blocked')
            ->where('seats.1.availabilityStatus', 'reserved')
            ->where('seats.1.expiresAt', fn (mixed $value): bool => is_string($value))
            ->where('seats.2.availabilityStatus', 'paid')
            ->missing('seats.0.id')
            ->missing('seats.1.id')
            ->missing('seats.2.id')
        );

    expect($heldReservation->fresh()->expires_at)->not->toBeNull();
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

it('captures counter-sale passengers through the web finalization step', function (): void {
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

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $quote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ]);
    $reservation = Reservation::query()->latest('id')->firstOrFail();

    $reservation->update(['draft_data' => [
        'passengers' => [$scenario['seat2A']->uuid => [
            'seat_uuid' => $scenario['seat2A']->uuid,
            'first_name' => 'Brouillon',
        ]],
    ]]);

    $response = $this->actingAs($scenario['agent'])
        ->post(route('sale.passengers', $reservation), $context + [
            'passengers' => [[
                'first_name' => 'Aïcha',
                'last_name' => 'Hounkpè',
                'phone' => '+229 97 24 18 06',
                'email' => 'aicha@example.com',
                'document_type' => 'passport',
                'document_number' => 'BJ0427851',
                'nationality' => 'BJ',
                'birth_date' => '1994-04-18',
                'seat_uuid' => $scenario['seat2A']->uuid,
            ]],
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('reservation_passengers', [
        'reservation_id' => $reservation->id,
        'first_name' => 'Aïcha',
        'email' => 'aicha@example.com',
        'document_type' => 'passport',
    ]);
    $this->assertDatabaseHas('reservation_segments', [
        'reservation_id' => $reservation->id,
        'reservation_passenger_id' => $reservation->passengers()->firstOrFail()->id,
    ]);
    expect($reservation->fresh()->draft_data)->toBeNull();

    $this->actingAs($scenario['agent'])
        ->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation.passengers.0.firstName', 'Aïcha')
            ->where('reservation.passengers.0.seatCode', '2A')
        );
});

it('releases the temporary hold when returning to seat selection', function (): void {
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

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $quote->uuid,
            'seats' => [['seat_uuid' => $scenario['seat2A']->uuid]],
        ]);
    $reservation = Reservation::query()->latest('id')->firstOrFail();

    $response = $this->actingAs($scenario['agent'])
        ->post(route('sale.release', $reservation), $context + [
            'quote_uuid' => $quote->uuid,
        ]);

    $response->assertRedirect();
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Cancelled);
    $this->assertDatabaseHas('reservation_segments', [
        'reservation_id' => $reservation->id,
        'status' => ReservationSegmentStatus::Cancelled->value,
    ]);
    expect($reservation->segments()->firstOrFail()->fresh()->released_at)->not->toBeNull();
    expect($reservation->fresh()->draft_data)->toBeNull();

    $this->actingAs($scenario['agent'])
        ->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation', null)
            ->where('seats.1.availabilityStatus', 'available')
        );
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

it('hides a scheduled departure after its configured sellability cutoff', function (): void {
    Carbon::setTestNow('2026-08-20 07:31:00');
    $scenario = counterSaleScenario();

    $this->actingAs($scenario['agent'])
        ->get(route('sale', ['date' => '2026-08-20', 'from' => 'Cotonou', 'to' => 'Abidjan']))
        ->assertInertia(fn (Assert $page): Assert => $page->where('departures', []));
});

it('keeps a delayed departure sellable until its effective cutoff', function (): void {
    Carbon::setTestNow('2026-08-20 08:15:00');
    $scenario = counterSaleScenario();
    $scenario['departure']->update(['delay_minutes' => 60, 'delay_reason' => 'Incident routier']);

    $this->actingAs($scenario['agent'])
        ->get(route('sale', ['date' => '2026-08-20', 'from' => 'Cotonou', 'to' => 'Abidjan']))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('departures.0.uuid', $scenario['departure']->uuid)
            ->where('departures.0.sellability.isSellable', true)
            ->where('departures.0.sellability.reason', 'within_grace')
        );
});

it('records a departure delay and audits it through activitylog', function (): void {
    $scenario = counterSaleScenario();
    $operator = User::factory()->create();
    $operator->assignRole('operations_director');

    $this->actingAs($operator)
        ->patch(route('departures.delay', $scenario['departure']), [
            'delay_minutes' => 45,
            'delay_reason' => 'Contrôle avant départ',
        ])
        ->assertRedirect();

    expect($scenario['departure']->fresh())
        ->delay_minutes->toBe(45)
        ->delay_reason->toBe('Contrôle avant départ');

    $this->assertDatabaseHas('activity_log', [
        'subject_type' => $scenario['departure']->getMorphClass(),
        'subject_id' => $scenario['departure']->id,
        'event' => 'delay_recorded',
    ]);
});

it('denies delay recording without departure management permission', function (): void {
    $scenario = counterSaleScenario();
    $user = User::factory()->create();
    $user->assignRole('passenger');

    $this->actingAs($user)
        ->patch(route('departures.delay', $scenario['departure']), [
            'delay_minutes' => 15,
            'delay_reason' => 'Motif',
        ])
        ->assertForbidden();
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

    $originPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou Guichet Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysical->id,
        'name' => 'Cotonou Guichet',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Abidjan Guichet Physical',
        'city' => 'Abidjan',
        'country' => 'CI',
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysical->id,
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
    $seat2B = Seat::query()->create([
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

    $serviceClass = ServiceClass::query()->create([
        'uuid' => (string) Str::uuid(),
        'code' => 'confort',
        'name' => 'KORA Confort',
    ]);

    Fare::query()->create([
        'line_id' => $line->id,
        'service_class_id' => $serviceClass->id,
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

    return compact('agent', 'line', 'origin', 'destination', 'bus', 'seat1A', 'seat2A', 'seat2B', 'departure');
}

/** @return array<string, string> */
function counterSaleContext(array $scenario): array
{
    return [
        'date' => '2026-08-20',
        'from' => 'Cotonou',
        'to' => 'Abidjan',
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ];
}

function createCounterSaleHold(mixed $test, array $scenario, string $seatCode = '2A'): Reservation
{
    $context = counterSaleContext($scenario);
    $seat = $seatCode === '2B' ? $scenario['seat2B'] : $scenario['seat2A'];

    $test->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 1]);
    $quote = PriceQuote::query()->latest('id')->firstOrFail();

    $test->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'price_quote_uuid' => $quote->uuid,
            'seats' => [['seat_uuid' => $seat->uuid]],
        ]);

    return Reservation::query()->latest('id')->firstOrFail();
}
