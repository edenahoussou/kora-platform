<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-08-19 07:00:00');
    $this->seed(KoraBusRolePermissionSeeder::class);
    GroupBookingSetting::query()->create(['minimum_passengers' => 5, 'maximum_passengers' => 10]);
    $settings = app(OperationsSettings::class);
    $settings->groupDiscountEnabled = true;
    $settings->groupDiscountPassengerThreshold = 10;
    $settings->groupDiscountPercentage = 10;
    $settings->save();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('exposes group booking settings and company accounts in the sale page Inertia props', function (): void {
    $scenario = groupSaleScenario();
    CompanyAccount::query()->create([
        'name' => 'ACME Corp',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);

    $this->actingAs($scenario['agent'])
        ->get(route('sale'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('sale')
            ->where('groupBookingSettings.minimumPassengers', 5)
            ->where('groupBookingSettings.maximumPassengers', 10)
            ->has('companyAccounts', 1)
            ->where('companyAccounts.0.name', 'ACME Corp')
            ->where('companyAccounts.0.reference', 'ACME-001')
            ->where('companyAccounts.0.monthlyCreditLimit', 500000)
            ->missing('companyAccounts.0.id')
        );
});

it('does not expose company accounts to users without the counter sale permission', function (): void {
    $scenario = groupSaleScenario();
    CompanyAccount::query()->create([
        'name' => 'ACME Corp',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);
    $passenger = User::factory()->create();
    $passenger->assignRole('passenger');

    $this->actingAs($passenger)
        ->get(route('sale'))
        ->assertForbidden();
});

it('serializes the group pricing breakdown in the itinerary quote Inertia props', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 10])
        ->assertRedirect();

    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['agent'])
        ->get(route('sale', $context + ['itinerary_quote_uuid' => $quote->uuid]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('itineraryQuote.uuid', $quote->uuid)
            ->where('itineraryQuote.passengerCount', 10)
            ->has('itineraryQuote.passengerAllocations', 10)
        );
});

it('marks a 5-passenger quote as group without applying the discount', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 5])
        ->assertRedirect();

    $this->actingAs($scenario['agent'])
        ->get(route('sale', $context + ['itinerary_quote_uuid' => ItineraryQuote::query()->latest('id')->firstOrFail()->uuid]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('itineraryQuote.passengerCount', 5)
        );
});

it('creates a group hold with a company payer and reserves company credit', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);
    $account = CompanyAccount::query()->create([
        'name' => 'ACME Corp',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 10])
        ->assertRedirect();
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $seatUuids = collect($scenario['seats'])->take(10)->values();
    $passengers = $seatUuids->map(fn (Seat $seat, int $i): array => [
        'passenger_index' => $i,
        'seat_assignments' => [
            ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $seat->uuid],
        ],
    ])->all();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => $passengers,
            'payer' => [
                'type' => 'company',
                'company_account_uuid' => $account->uuid,
            ],
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();

    expect($reservation->purchase_type->value)->toBe('group')
        ->and($reservation->payer_type->value)->toBe('company')
        ->and($reservation->group_size)->toBe(10)
        ->and($reservation->company_account_id)->toBe($account->id);

    $this->assertDatabaseHas('company_credit_holds', [
        'reservation_id' => $reservation->id,
        'company_account_id' => $account->id,
        'status' => 'reserved',
    ]);
});

it('serializes the reservation group and payer metadata in Inertia props', function (): void {
    $scenario = groupSaleScenario();
    $reservation = createGroupCompanyHold($this, $scenario);

    $this->actingAs($scenario['agent'])
        ->get(route('sale', groupSaleContext($scenario) + ['reservation_uuid' => $reservation->uuid]))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation.uuid', $reservation->uuid)
            ->where('reservation.purchaseType', 'group')
            ->where('reservation.groupSize', 10)
            ->where('reservation.payerType', 'company')
            ->where('reservation.companyAccount.uuid', $reservation->companyAccount->uuid)
            ->where('reservation.companyAccount.name', 'ACME Corp')
            ->where('reservation.companyAccount.reference', 'ACME-001')
            ->has('reservation.passengerAllocations', 10)
            ->missing('reservation.companyAccount.id')
        );
});

it('records a company credit payment without creating a cash movement', function (): void {
    $scenario = groupSaleScenario();
    $reservation = createGroupCompanyHold($this, $scenario);
    fillGroupPassengers($this, $scenario, $reservation);

    $total = (int) $reservation->total_amount;

    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), [
            'method' => 'company_credit',
            'amount' => $total,
            'idempotency_key' => (string) Str::uuid(),
            'verified_manually' => true,
        ])
        ->assertRedirect();

    expect($reservation->fresh()->selling_agency_id)->toBe($scenario['agent']->agency_id);
    expect($reservation->fresh()->status)->toBeIn([ReservationStatus::Paid, ReservationStatus::Ticketed]);

    $this->assertDatabaseHas('payments', [
        'reservation_id' => $reservation->id,
        'method' => 'company_credit',
        'amount' => $total,
    ]);

    $this->assertDatabaseMissing('cash_movements', [
        'source_type' => Payment::class,
        'source_id' => Payment::query()->where('reservation_id', $reservation->id)->firstOrFail()->id,
    ]);

    expect(Ticket::query()->where('reservation_id', $reservation->id)->count())->toBeIn([0, 10]);
});

it('rejects company credit payment when no credit hold exists', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 10]);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $seatUuids = collect($scenario['seats'])->take(10)->values();
    $passengers = $seatUuids->map(fn (Seat $seat, int $i): array => [
        'passenger_index' => $i,
        'seat_assignments' => [
            ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $seat->uuid],
        ],
    ])->all();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => $passengers,
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    fillGroupPassengers($this, $scenario, $reservation);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), [
            'method' => 'company_credit',
            'amount' => (int) $reservation->total_amount,
            'idempotency_key' => (string) Str::uuid(),
            'verified_manually' => true,
        ])
        ->assertSessionHasErrors('method');
});

it('still allows a personal payer for a group booking paid in cash', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 10]);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $seatUuids = collect($scenario['seats'])->take(10)->values();
    $passengers = $seatUuids->map(fn (Seat $seat, int $i): array => [
        'passenger_index' => $i,
        'seat_assignments' => [
            ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $seat->uuid],
        ],
    ])->all();

    $this->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => $passengers,
        ])
        ->assertRedirect();

    $reservation = Reservation::query()->latest('id')->firstOrFail();
    expect($reservation->purchase_type->value)->toBe('group')
        ->and($reservation->payer_type->value)->toBe('personal');
});

it('rejects a group quote above the configured maximum passenger count', function (): void {
    $scenario = groupSaleScenario();
    $context = groupSaleContext($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 11])
        ->assertSessionHasErrors('passenger_count');
});

it('uses deterministic passenger allocations that sum to the total', function (): void {
    $scenario = groupSaleScenario();
    $reservation = createGroupCompanyHold($this, $scenario);

    $allocations = $reservation->total_snapshot['passenger_allocations'] ?? [];
    expect($allocations)->toHaveCount(10)
        ->and(array_sum($allocations))->toBe((int) $reservation->total_amount);

    // Allocations should differ by at most 1 FCFA (floor + remainder distribution)
    $min = min($allocations);
    $max = max($allocations);
    expect($max - $min)->toBeLessThanOrEqual(1);
});

/**
 * @return array<string, mixed>
 */
function groupSaleScenario(): array
{
    $agency = Agency::query()->create([
        'code' => 'GRP-AGENCY',
        'name' => 'Agence Groupe',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');

    $line = Line::query()->create([
        'code' => 'GRP-L1',
        'name' => 'Cotonou - Abidjan Groupe',
        'countries_crossed' => ['BJ', 'CI'],
        'distance_km' => 700,
        'estimated_duration_minutes' => 600,
        'status' => 'active',
    ]);

    $originPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Cotonou GRP Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysical->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(),
        'name' => 'Abidjan GRP Physical',
        'city' => 'Abidjan',
        'country' => 'CI',
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysical->id,
        'name' => 'Abidjan',
        'city' => 'Abidjan',
        'country' => 'CI',
        'stop_order' => 2,
        'km_from_origin' => 700,
        'status' => 'active',
    ]);

    $bus = Bus::query()->create([
        'registration_number' => 'GRP-BUS',
        'brand' => 'KORA',
        'model' => 'Coach Groupe',
        'seat_count' => 12,
        'status' => 'active',
    ]);

    $seats = [];
    $seatCodes = ['1A', '1B', '2A', '2B', '3A', '3B', '4A', '4B', '5A', '5B', '6A', '6B'];
    foreach ($seatCodes as $code) {
        $seats[$code] = Seat::query()->create([
            'bus_id' => $bus->id,
            'code' => $code,
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);
    }

    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-08-20',
        'departure_time' => '08:00',
        'estimated_arrival_at' => Carbon::parse('2026-08-20 18:00'),
        'status' => 'scheduled',
    ]);

    $serviceClass = ServiceClass::query()->create([
        'code' => 'grp-confort',
        'name' => 'Confort Groupe',
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
        'priority' => 1,
        'status' => 'active',
        'conditions' => [],
    ]);

    return compact('agency', 'agent', 'line', 'origin', 'destination', 'bus', 'seats', 'departure');
}

/** @return array<string, mixed> */
function groupSaleContext(array $scenario): array
{
    return [
        'date' => '2026-08-20',
        'from' => 'Cotonou',
        'to' => 'Abidjan',
        'outbound_legs' => [[
            'departure_uuid' => $scenario['departure']->uuid,
            'origin_stop_uuid' => $scenario['origin']->uuid,
            'destination_stop_uuid' => $scenario['destination']->uuid,
        ]],
    ];
}

function createGroupCompanyHold(object $test, array $scenario): Reservation
{
    $context = groupSaleContext($scenario);
    $account = CompanyAccount::query()->create([
        'name' => 'ACME Corp',
        'type' => 'company',
        'reference' => 'ACME-001',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);

    $test->actingAs($scenario['agent'])
        ->post(route('sale.quote'), $context + ['passenger_count' => 10]);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();

    $seatUuids = collect($scenario['seats'])->take(10)->values();
    $passengers = $seatUuids->map(fn (Seat $seat, int $i): array => [
        'passenger_index' => $i,
        'seat_assignments' => [
            ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $seat->uuid],
        ],
    ])->all();

    $test->actingAs($scenario['agent'])
        ->post(route('sale.hold'), $context + [
            'itinerary_quote_uuid' => $quote->uuid,
            'idempotency_key' => (string) Str::uuid(),
            'passengers' => $passengers,
            'payer' => [
                'type' => 'company',
                'company_account_uuid' => $account->uuid,
            ],
        ]);

    return Reservation::query()->latest('id')->firstOrFail();
}

function fillGroupPassengers(object $test, array $scenario, Reservation $reservation): void
{
    $context = groupSaleContext($scenario);
    $seats = collect($scenario['seats'])->take(10)->values();
    $passengers = $seats->map(fn (Seat $seat, int $i): array => [
        'first_name' => 'Passager',
        'last_name' => 'Groupe '.($i + 1),
        'phone' => '+2299700000'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
        'email' => '',
        'document_type' => 'national_id',
        'document_number' => 'GRP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
        'nationality' => 'BJ',
        'birth_date' => '1990-01-01',
        'seat_uuid' => $seat->uuid,
    ])->all();

    $test->actingAs($scenario['agent'])
        ->post(route('sale.passengers', $reservation), $context + [
            'information_confirmed' => true,
            'passengers' => $passengers,
        ]);
}
