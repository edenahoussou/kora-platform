<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusReferenceSeeder;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\KoraCounterSaleSeeder;
use Database\Seeders\KoraDemoUserSeeder;
use Illuminate\Http\UploadedFile;
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

it('opens the current cash session from the counter sale quick action', function (): void {
    $scenario = paymentSaleScenario();

    $this->actingAs($scenario['agent'])
        ->post(route('cash.open'), [
            'ledgers' => [[
                'kind' => 'cash',
                'account_uuid' => null,
                'opening_balance' => 25000,
                'evidence' => UploadedFile::fake()->image('cash-opening.jpg'),
            ]],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cash_sessions', [
        'agent_id' => $scenario['agent']->id,
        'agency_id' => $scenario['agency']->id,
        'opening_float' => 25000,
        'expected_balance' => 25000,
        'status' => 'open',
    ]);
});

it('configures the demo counter sale agent and agency Mobile Money accounts', function (): void {
    $this->seed([
        KoraBusReferenceSeeder::class,
        KoraDemoUserSeeder::class,
        KoraCounterSaleSeeder::class,
    ]);

    $agent = User::query()->where('email', 'agent.sandrine@kora.demo')->firstOrFail();
    $agency = Agency::query()->where('code', 'CTN')->firstOrFail();

    expect($agent->agency_id)->toBe($agency->id)
        ->and(AgencyPaymentAccount::query()->where('agency_id', $agency->id)->count())->toBe(3)
        ->and(CashSession::query()->where('agent_id', $agent->id)->exists())->toBeFalse();

    $this->assertDatabaseHas('agency_payment_accounts', [
        'agency_id' => $agency->id,
        'network' => 'mtn_momo',
        'account_identifier' => '+22997000001',
        'status' => 'active',
    ]);
});

it('records an exact cash payment, change, cash movement and one ticket per passenger', function (): void {
    $scenario = paymentSaleScenario();
    $reservation = paymentReservation($this, $scenario);
    $reservation->update(['draft_data' => [
        'passengers' => ['seat-draft' => ['first_name' => 'Brouillon']],
    ]]);
    createCashSessionWithLedgers($scenario, openingCash: 1000);

    $response = $this->actingAs($scenario['agent'])->post(route('sale.payment', $reservation), [
        'method' => 'cash',
        'amount' => 11000,
        'cash_received_amount' => 12000,
        'idempotency_key' => (string) Str::uuid(),
        'verified_manually' => true,
    ]);

    $response->assertRedirect();
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Ticketed);
    expect($reservation->fresh()->draft_data)->toBeNull();
    $this->assertDatabaseHas('payments', [
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'amount' => 11000,
        'cash_received_amount' => 12000,
        'change_amount' => 1000,
    ]);
    $this->assertDatabaseHas('cash_movements', [
        'type' => 'counter_sale_cash',
        'amount' => 11000,
        'created_by' => $scenario['agent']->id,
    ]);
    $this->assertDatabaseHas('tickets', ['reservation_id' => $reservation->id]);
    expect(Ticket::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
    expect(CashSession::query()->firstOrFail()->total_cash)->toBe(11000);

    $this->actingAs($scenario['agent'])
        ->get($response->headers->get('Location'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('reservation.payment.confirmedAt', fn (mixed $value): bool => is_string($value))
            ->where('reservation.payment.verifiedByUuid', $scenario['agent']->uuid)
            ->where('reservation.payment.agencyCode', $scenario['agency']->code)
            ->where('reservation.qrPayload.reservationCode', strtoupper(substr(str_replace('-', '', $reservation->uuid), 0, 8)))
            ->where('reservation.qrPayload.reservationUuid', $reservation->uuid)
            ->where('reservation.qrPayload.agentUuid', $scenario['agent']->uuid)
            ->where('reservation.fiscalization.koraReference', fn (mixed $value): bool => is_string($value))
            ->where('reservation.tickets.0.ticketNumber', fn (mixed $value): bool => is_string($value))
        );
});

it('rejects an insufficient cash payment without creating a payment or ticket', function (): void {
    $scenario = paymentSaleScenario();
    $reservation = paymentReservation($this, $scenario);
    createCashSessionWithLedgers($scenario);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), [
            'method' => 'cash',
            'amount' => 11000,
            'cash_received_amount' => 10000,
            'idempotency_key' => (string) Str::uuid(),
            'verified_manually' => true,
        ])
        ->assertSessionHasErrors('cash_received_amount');

    $this->assertDatabaseCount('payments', 0);
    $this->assertDatabaseCount('tickets', 0);
    expect($reservation->fresh()->status)->toBe(ReservationStatus::Held);
});

it('records a manually verified Mobile Money payment on the agency account', function (): void {
    $scenario = paymentSaleScenario();
    $reservation = paymentReservation($this, $scenario);
    $account = AgencyPaymentAccount::query()->create([
        'agency_id' => $scenario['agency']->id,
        'network' => 'mtn_momo',
        'label' => 'Compte MTN agence',
        'account_identifier' => '97000000',
        'status' => 'active',
    ]);
    createCashSessionWithLedgers($scenario, accounts: [$account]);

    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), [
            'method' => 'mobile_money',
            'amount' => 11000,
            'mobile_money_network' => 'mtn_momo',
            'agency_payment_account_uuid' => $account->uuid,
            'payer_phone' => '+22997241806',
            'payer_name' => 'Aïcha Hounkpè',
            'provider_reference' => 'MTN-20260819-001',
            'verified_manually' => true,
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('payments', [
        'reservation_id' => $reservation->id,
        'method' => 'mobile_money',
        'mobile_money_network' => 'mtn_momo',
        'agency_payment_account_id' => $account->id,
        'provider_reference' => 'MTN-20260819-001',
        'payer_phone' => '+22997241806',
    ]);
    $this->assertDatabaseHas('cash_movements', [
        'type' => 'counter_sale_mobile_money',
        'amount' => 11000,
    ]);
    expect(CashSession::query()->firstOrFail()->total_cash)->toBe(0);
    expect(CashSession::query()->firstOrFail()->total_mobile_money)->toBe(11000);
});

it('requires an open cash session and blocks duplicate Mobile Money references', function (): void {
    $scenario = paymentSaleScenario();
    $reservation = paymentReservation($this, $scenario);
    $account = AgencyPaymentAccount::query()->create([
        'agency_id' => $scenario['agency']->id,
        'network' => 'moov_flooz',
        'label' => 'Flooz agence',
        'account_identifier' => '96000000',
        'status' => 'active',
    ]);
    $payload = [
        'method' => 'mobile_money',
        'amount' => 11000,
        'mobile_money_network' => 'moov_flooz',
        'agency_payment_account_uuid' => $account->uuid,
        'payer_phone' => '+22996000000',
        'provider_reference' => 'FLOOZ-001',
        'verified_manually' => true,
        'idempotency_key' => (string) Str::uuid(),
    ];

    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), $payload)
        ->assertSessionHasErrors('cash_session');

    createCashSessionWithLedgers($scenario, accounts: [$account]);
    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $reservation), $payload)
        ->assertRedirect();

    $secondReservation = paymentReservation($this, $scenario, '2B');
    $this->actingAs($scenario['agent'])
        ->post(route('sale.payment', $secondReservation), array_merge($payload, [
            'idempotency_key' => (string) Str::uuid(),
        ]))
        ->assertSessionHasErrors('provider_reference');
});

it('is idempotent when the same request is submitted twice', function (): void {
    $scenario = paymentSaleScenario();
    $reservation = paymentReservation($this, $scenario);
    createCashSessionWithLedgers($scenario);
    $payload = [
        'method' => 'cash',
        'amount' => 11000,
        'cash_received_amount' => 11000,
        'idempotency_key' => (string) Str::uuid(),
        'verified_manually' => true,
    ];

    $this->actingAs($scenario['agent'])->post(route('sale.payment', $reservation), $payload)->assertRedirect();
    $this->actingAs($scenario['agent'])->post(route('sale.payment', $reservation), $payload)->assertRedirect();

    expect(Payment::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
    expect(Ticket::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
    expect(CashSession::query()->firstOrFail()->total_cash)->toBe(11000);
});

/** @return array{agent: User, agency: Agency, departure: object, seats: array<string, object>} */
function paymentSaleScenario(): array
{
    $agency = Agency::query()->create([
        'code' => 'PAY-AGENCY',
        'name' => 'Agence Paiement',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $agent = User::factory()->create(['agency_id' => $agency->id]);
    $agent->assignRole('agency_agent');
    $line = Line::query()->create([
        'code' => 'PAY-L1', 'name' => 'Cotonou - Abidjan', 'countries_crossed' => ['BJ', 'CI'],
        'distance_km' => 700, 'estimated_duration_minutes' => 600, 'status' => 'active',
    ]);
    $originPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Cotonou PAY Physical', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $originPhysical->id, 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active',
    ]);
    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => (string) Str::uuid(), 'name' => 'Abidjan PAY Physical', 'city' => 'Abidjan', 'country' => 'CI', 'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id, 'name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'stop_order' => 2, 'km_from_origin' => 700, 'status' => 'active',
    ]);
    $bus = Bus::query()->create([
        'registration_number' => 'PAY-BUS', 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 2, 'status' => 'active',
    ]);
    $seats = [
        '2A' => Seat::query()->create(['bus_id' => $bus->id, 'code' => '2A', 'type' => 'standard', 'sellable' => true, 'status' => 'active']),
        '2B' => Seat::query()->create(['bus_id' => $bus->id, 'code' => '2B', 'type' => 'standard', 'sellable' => true, 'status' => 'active']),
    ];
    $departure = Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-08-20', 'departure_time' => '08:00', 'estimated_arrival_at' => Carbon::parse('2026-08-20 18:00'), 'status' => 'scheduled',
    ]);
    $serviceClass = ServiceClass::query()->create(['code' => 'pay-confort', 'name' => 'Confort']);
    Fare::query()->create([
        'line_id' => $line->id, 'service_class_id' => $serviceClass->id, 'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id, 'base_amount' => 10000, 'currency' => 'XOF', 'effective_from' => '2026-01-01', 'status' => 'active',
    ]);
    PricingRule::query()->create([
        'line_id' => $line->id, 'type' => 'increase', 'percentage' => 10, 'priority' => 1, 'status' => 'active', 'conditions' => [],
    ]);

    return compact('agent', 'agency', 'departure', 'origin', 'destination', 'seats');
}

function paymentReservation(object $test, array $scenario, string $seatCode = '2A'): Reservation
{
    $context = [
        'date' => '2026-08-20', 'from' => 'Cotonou', 'to' => 'Abidjan', 'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid, 'destination_stop_uuid' => $scenario['destination']->uuid,
    ];
    $test->actingAs($scenario['agent'])->post(route('sale.quote'), $context + ['passenger_count' => 1]);
    $quote = PriceQuote::query()->latest('id')->firstOrFail();
    $test->actingAs($scenario['agent'])->post(route('sale.hold'), $context + [
        'price_quote_uuid' => $quote->uuid,
        'seats' => [['seat_uuid' => $scenario['seats'][$seatCode]->uuid]],
    ]);
    $reservation = Reservation::query()->latest('id')->firstOrFail();
    $test->actingAs($scenario['agent'])->post(route('sale.passengers', $reservation), $context + [
        'passengers' => [[
            'first_name' => 'Aïcha', 'last_name' => 'Hounkpè', 'phone' => '+22997241806', 'email' => 'aicha@example.com',
            'document_type' => 'passport', 'document_number' => 'BJ0427851', 'nationality' => 'BJ', 'birth_date' => '1994-04-18',
            'seat_uuid' => $scenario['seats'][$seatCode]->uuid,
        ]],
    ]);

    return $reservation->fresh();
}

/** @param array{agent: User, agency: Agency} $scenario @param list<AgencyPaymentAccount> $accounts */
function createCashSessionWithLedgers(array $scenario, int $openingCash = 0, array $accounts = []): CashSession
{
    $session = CashSession::query()->create([
        'agent_id' => $scenario['agent']->id,
        'agency_id' => $scenario['agency']->id,
        'business_date' => '2026-08-19',
        'opening_float' => $openingCash,
        'expected_balance' => $openingCash,
        'total_cash' => 0,
        'total_mobile_money' => 0,
        'status' => 'open',
        'opened_at' => now(),
    ]);

    CashLedger::query()->create([
        'cash_session_id' => $session->id,
        'ledger_key' => 'cash',
        'kind' => 'cash',
        'opening_balance' => $openingCash,
        'expected_balance' => $openingCash,
        'status' => 'open',
        'opened_by' => $scenario['agent']->id,
        'opened_at' => now(),
    ]);

    foreach ($accounts as $account) {
        CashLedger::query()->create([
            'cash_session_id' => $session->id,
            'ledger_key' => $account->uuid,
            'kind' => 'mobile_money',
            'agency_payment_account_id' => $account->id,
            'opening_balance' => 0,
            'expected_balance' => 0,
            'status' => 'open',
            'opened_by' => $scenario['agent']->id,
            'opened_at' => now(),
        ]);
    }

    return $session;
}
