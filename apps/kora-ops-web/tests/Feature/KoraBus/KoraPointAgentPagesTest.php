<?php

use App\Models\User;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus as BusFacade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    BusFacade::fake([IssueReservationTicketsJob::class]);

    $this->point = KoraPoint::factory()->create([
        'commission_basis_points' => 300,
        'ceiling_amount' => 1500000,
        'settlement_calendar' => [
            'frequency' => 'weekly',
            'weekday' => 5,
            'time' => '17:00',
            'timezone' => 'Africa/Porto-Novo',
        ],
    ]);

    $this->agent = User::factory()->create([
        'agency_id' => $this->point->agency_id,
    ]);
    $this->agent->assignRole('kora_point_agent');
});

function createAgentSale(KoraPoint $point, User $agent, int $amount = 15000): KoraPointSale
{
    return createCustomAgentSale($point, $agent, $amount);
}

function createCustomAgentSale(
    KoraPoint $point,
    User $agent,
    int $amount = 15000,
    string $method = 'cash',
    string $provider = 'counter',
    string $firstName = 'Femi',
    string $lastName = 'Kora',
    string $phone = '+22997000001',
    ?CarbonImmutable $paidAt = null
): KoraPointSale {
    return DB::transaction(function () use ($point, $agent, $amount, $method, $provider, $firstName, $lastName, $phone, $paidAt): KoraPointSale {
        $reservation = Reservation::query()->create([
            'created_by_user_id' => $agent->getKey(),
            'payer_user_id' => User::factory()->create()->getKey(),
            'selling_agency_id' => $point->agency_id,
            'channel' => 'counter',
            'status' => 'paid',
            'total_amount' => $amount,
            'currency' => 'XOF',
            'total_snapshot' => ['passenger_allocations' => [$amount]],
        ]);

        $reservation->passengers()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'passenger_index' => 0,
            'status' => 'active',
        ]);

        $payment = Payment::query()->create([
            'reservation_id' => $reservation->getKey(),
            'method' => $method,
            'provider' => $provider,
            'status' => 'confirmed',
            'amount' => $amount,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
            'payer_name' => "{$firstName} {$lastName}",
            'payer_phone' => $phone,
            'confirmed_at' => $paidAt ?? now(),
        ]);

        $sale = app(RecordKoraPointSaleAction::class)->execute($reservation, $payment, $agent);
        if ($paidAt !== null) {
            $sale->update(['paid_at' => $paidAt]);
        }

        return $sale;
    });
}

function createAgentDeparture(): array
{
    $bus = Bus::query()->create([
        'registration_number' => 'KP-BUS-'.Str::random(5),
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
    $line = Line::query()->create([
        'code' => 'KP-L-'.Str::random(5),
        'name' => 'Lome - Cotonou',
        'countries_crossed' => ['BJ', 'TG'],
        'distance_km' => 150,
        'estimated_duration_minutes' => 180,
        'status' => 'active',
    ]);
    $physOrigin = PhysicalStop::query()->create([
        'name' => 'Lomé Gare Physical',
        'city' => 'Lomé',
        'country' => 'TG',
        'status' => 'active',
    ]);
    $physDestination = PhysicalStop::query()->create([
        'name' => 'Cotonou Gare Physical',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);
    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $physOrigin->id,
        'name' => 'Lomé Gare',
        'city' => 'Lomé',
        'country' => 'TG',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);
    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'physical_stop_id' => $physDestination->id,
        'name' => 'Cotonou Gare',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 150,
        'status' => 'active',
    ]);

    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-11-02',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);

    return [
        'departure' => $departure,
        'seat' => $seat,
        'origin' => $origin,
        'destination' => $destination,
    ];
}

it('redirects kora_point_agent to kora-point dashboard when visiting /dashboard', function (): void {
    $this->actingAs($this->agent)
        ->get(route('dashboard'))
        ->assertRedirect(route('kora-point.dashboard'));
});

it('renders agent dashboard with point and health props', function (): void {
    createAgentSale($this->point, $this->agent, 20000);

    $this->actingAs($this->agent)
        ->get(route('kora-point.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/dashboard')
            ->has('point')
            ->where('point.uuid', $this->point->uuid)
            ->where('point.code', $this->point->agency->code)
            ->has('health')
            ->where('health.credit_limit', 1500000)
            ->where('health.sales_allowed', true)
        );
});

it('renders sale page with point and health and full counter sale props', function (): void {
    $this->actingAs($this->agent)
        ->get(route('kora-point.sale'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/sale')
            ->has('point')
            ->has('health')
            ->has('cities')
            ->has('search')
            ->has('outboundItineraries')
            ->has('inboundItineraries')
            ->has('permissions')
            ->has('cashSession')
            ->has('mobileMoneyAccounts')
            ->has('groupBookingSettings')
        );
});

it('blocks sale hold if quote exceeds kora point available balance', function (): void {
    $this->point->update(['ceiling_amount' => 5000]);
    $bus = Bus::query()->create([
        'registration_number' => 'KP-TEST-BUS',
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
    $quote = ItineraryQuote::query()->create([
        'user_id' => $this->agent->getKey(),
        'passenger_count' => 1,
        'subtotal_amount' => 15000,
        'total_amount' => 15000,
        'currency' => 'XOF',
        'snapshot' => ['type' => 'itinerary'],
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($this->agent)
        ->post(route('kora-point.sale.hold'), [
            'itinerary_quote_uuid' => $quote->uuid,
            'passengers' => [
                [
                    'passenger_index' => 0,
                    'seat_assignments' => [
                        ['leg_order' => 1, 'seat_uuid' => $seat->uuid],
                    ],
                ],
            ],
            'date' => '2026-11-02',
        ])
        ->assertSessionHasErrors('credit');
});

it('renders cash page with current session and allows open and close', function (): void {
    $this->actingAs($this->agent)
        ->get(route('kora-point.cash'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->has('point')
            ->has('health')
            ->has('brouillard')
            ->has('cashSession')
            ->where('cashSession.status', 'CLOSED')
        );

    // Open cash session
    $this->actingAs($this->agent)
        ->post(route('kora-point.cash.open'), [
            'opening_amount' => 50000,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cash_sessions', [
        'agent_id' => $this->agent->getKey(),
        'agency_id' => $this->agent->agency_id,
        'opening_float' => 50000,
        'status' => 'open',
    ]);

    // Close cash session
    $this->actingAs($this->agent)
        ->post(route('kora-point.cash.close'), [
            'actual_cash' => 50000,
            'closing_note' => 'Clôture normale',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('cash_sessions', [
        'agent_id' => $this->agent->getKey(),
        'status' => 'closed',
    ]);
});

it('renders tickets page with point props', function (): void {
    createAgentSale($this->point, $this->agent, 10000);

    $this->actingAs($this->agent)
        ->get(route('kora-point.tickets'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/tickets')
            ->has('point')
        );
});

it('renders profile page with point and health props', function (): void {
    KoraPartnerDocument::query()->create([
        'point_id' => $this->point->getKey(),
        'uploaded_by' => $this->agent->getKey(),
        'kind' => 'rccm',
        'title' => 'Registre du Commerce',
        'reference' => 'RB/COT/2026/B/001',
    ]);

    $this->actingAs($this->agent)
        ->get(route('kora-point.profile'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/profile')
            ->has('point')
            ->has('health')
        );
});

it('renders commissions page with point props', function (): void {
    createAgentSale($this->point, $this->agent, 30000);

    $this->actingAs($this->agent)
        ->get(route('kora-point.commissions'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/commissions')
            ->has('point')
        );
});

it('renders settlements page with remittances and kora payment details', function (): void {
    $this->actingAs($this->agent)
        ->get(route('kora-point.settlements'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/settlements')
            ->has('point')
            ->has('health')
            ->has('koraPaymentDetails')
            ->has('koraPaymentDetails.bank_accounts')
            ->has('koraPaymentDetails.mobile_money_merchants')
            ->has('koraPaymentDetails.check_order')
        );
});

it('previews unsettled sales for a selected period via settlements.preview', function (): void {
    $sale1 = createAgentSale($this->point, $this->agent, 20000);
    KoraPointSale::query()->whereKey($sale1->getKey())->update(['paid_at' => CarbonImmutable::parse('2026-10-25 10:00:00')]);

    $sale2 = createAgentSale($this->point, $this->agent, 30000);
    KoraPointSale::query()->whereKey($sale2->getKey())->update(['paid_at' => CarbonImmutable::parse('2026-10-26 14:00:00')]);

    // 1 sale on 2026-10-31 (outside preview filter)
    $sale3 = createAgentSale($this->point, $this->agent, 10000);
    KoraPointSale::query()->whereKey($sale3->getKey())->update(['paid_at' => CarbonImmutable::parse('2026-10-31 08:00:00')]);

    $response = $this->actingAs($this->agent)
        ->getJson(route('kora-point.settlements.preview', [
            'from' => '2026-10-25',
            'to' => '2026-10-26',
        ]))
        ->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => [
            'tickets_count' => 2,
            'gross_amount' => 50000,
            'commission_amount' => 1500, // 3% of 50000
            'net_amount' => 48500,
            'sale_uuids' => [$sale1->uuid, $sale2->uuid],
        ],
    ]);
});

it('allows partner agent to declare settlement for sales period with proof file and check method', function (): void {
    Storage::fake('public');

    $sale1 = createCustomAgentSale(
        $this->point,
        $this->agent,
        amount: 20000,
        method: 'cash',
        paidAt: CarbonImmutable::parse('2026-10-31 09:00:00')
    );

    $sale2 = createCustomAgentSale(
        $this->point,
        $this->agent,
        amount: 30000,
        method: 'cash',
        paidAt: CarbonImmutable::parse('2026-10-31 10:00:00')
    );

    $file = UploadedFile::fake()->create('bordereau_cheque.pdf', 300, 'application/pdf');

    $this->actingAs($this->agent)
        ->post(route('kora-point.settlements.declare'), [
            'sale_uuids' => [$sale1->uuid, $sale2->uuid],
            'method' => 'check',
            'reference' => 'CHQ-BOA-2026-9988',
            'bank_name' => 'Bank of Africa (BOA)',
            'notes' => 'Chèque déposé en agence principale',
            'proof' => $file,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('kora-point.settlements'));

    $this->assertDatabaseHas('kora_point_remittances', [
        'method' => 'check',
        'reference' => 'CHQ-BOA-2026-9988',
        'status' => 'submitted',
        'declared_by' => $this->agent->getKey(),
        'amount' => 48500,
    ]);

    $this->assertDatabaseHas('kora_partner_documents', [
        'point_id' => $this->point->getKey(),
        'kind' => 'remittance_proof',
        'reference' => 'CHQ-BOA-2026-9988',
        'uploaded_by' => $this->agent->getKey(),
    ]);

    $this->assertDatabaseHas('kora_point_remittance_lines', [
        'sale_id' => $sale1->getKey(),
    ]);
    $this->assertDatabaseHas('kora_point_remittance_lines', [
        'sale_id' => $sale2->getKey(),
    ]);
});

it('renders refunds page with point and health', function (): void {
    $this->actingAs($this->agent)
        ->get(route('kora-point.refunds'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/refunds')
            ->has('point')
            ->has('health')
        );
});

it('redirects GET on kora-point/sale/seats to kora-point.sale without 405 error', function (): void {
    $this->actingAs($this->agent)
        ->get('/kora-point/sale/seats')
        ->assertRedirect(route('kora-point.sale'));
});

it('forbids unauthenticated access with redirect to login', function (): void {
    $this->get(route('kora-point.dashboard'))
        ->assertRedirect(route('login'));
});

it('returns 403 for user without kora_point_agent role', function (): void {
    $regularUser = User::factory()->create();

    $this->actingAs($regularUser)
        ->get(route('kora-point.dashboard'))
        ->assertForbidden();
});

it('returns 403 when point is inactive', function (): void {
    $this->point->update(['status' => 'inactive']);

    $this->actingAs($this->agent)
        ->get(route('kora-point.dashboard'))
        ->assertForbidden();
});

it('computes daily cash brouillard with cash and mobile money totals for partner point', function (): void {
    createAgentSale($this->point, $this->agent, 25000);

    $this->actingAs($this->agent)
        ->get(route('kora-point.cash'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->has('brouillard')
            ->where('brouillard.gross_total', 25000)
            ->where('brouillard.tickets_count', 1)
            ->where('brouillard.cash_total', 25000)
            ->where('brouillard.commission_total', 750)
            ->where('brouillard.net_total', 24250)
            ->has('brouillard.movements', 1)
        );
});

it('filters cash brouillard by date range, channel, and search query for partner point', function (): void {
    // 1. Cash sale today
    createCustomAgentSale(
        $this->point,
        $this->agent,
        amount: 15000,
        method: 'cash',
        provider: 'counter',
        firstName: 'Femi',
        lastName: 'Kora',
        phone: '+22997000001',
        paidAt: CarbonImmutable::parse('2026-10-31 10:00:00')
    );

    // 2. Mobile money sale today
    createCustomAgentSale(
        $this->point,
        $this->agent,
        amount: 20000,
        method: 'mobile_money',
        provider: 'tmoney_tg',
        firstName: 'Yao',
        lastName: 'Mensah',
        phone: '+22890112233',
        paidAt: CarbonImmutable::parse('2026-10-31 11:30:00')
    );

    // 3. Past sale 3 days ago
    createCustomAgentSale(
        $this->point,
        $this->agent,
        amount: 30000,
        method: 'cash',
        provider: 'counter',
        firstName: 'Awa',
        lastName: 'Toure',
        phone: '+22507000000',
        paidAt: CarbonImmutable::parse('2026-10-28 09:00:00')
    );

    // Default: today (2026-10-31), only 2 sales
    $this->actingAs($this->agent)
        ->get(route('kora-point.cash'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->has('filters')
            ->where('brouillard.gross_total', 35000)
            ->where('brouillard.cash_total', 15000)
            ->where('brouillard.mobile_money_total', 20000)
            ->where('brouillard.tickets_count', 2)
            ->has('brouillard.movements', 2)
        );

    // Filter by channel: mobile_money only
    $this->actingAs($this->agent)
        ->get(route('kora-point.cash', ['channel' => 'mobile_money']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->where('brouillard.gross_total', 20000)
            ->where('brouillard.tickets_count', 1)
            ->where('brouillard.movements.0.passenger_name', 'Yao Mensah')
        );

    // Filter by search query: passenger name or phone
    $this->actingAs($this->agent)
        ->get(route('kora-point.cash', ['q' => 'Mensah']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->where('brouillard.tickets_count', 1)
            ->where('brouillard.movements.0.passenger_name', 'Yao Mensah')
        );

    // Filter by date range covering past sale (2026-10-25 to 2026-10-31)
    $this->actingAs($this->agent)
        ->get(route('kora-point.cash', [
            'from' => '2026-10-25',
            'to' => '2026-10-31',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/agent/cash')
            ->where('brouillard.gross_total', 65000)
            ->where('brouillard.tickets_count', 3)
            ->has('brouillard.movements', 3)
        );
});

it('allows partner agent to record cash sale without open cash session', function (): void {
    $reservation = Reservation::query()->create([
        'created_by_user_id' => $this->agent->getKey(),
        'origin_kora_point_id' => $this->point->getKey(),
        'selling_agency_id' => $this->point->agency_id,
        'channel' => 'counter',
        'status' => 'held',
        'total_amount' => 15000,
        'currency' => 'XOF',
        'total_snapshot' => ['passenger_allocations' => [15000]],
        'held_at' => now(),
        'expires_at' => now()->addMinutes(15),
    ]);

    $setup = createAgentDeparture();

    $reservation->segments()->create([
        'departure_id' => $setup['departure']->id,
        'seat_id' => $setup['seat']->id,
        'origin_stop_id' => $setup['origin']->id,
        'destination_stop_id' => $setup['destination']->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'leg_order' => 1,
        'passenger_index' => 0,
        'direction' => 'outbound',
        'status' => 'held',
    ]);

    $reservation->passengers()->create([
        'first_name' => 'Kokou',
        'last_name' => 'Lome',
        'phone' => '+22890112233',
        'passenger_index' => 0,
        'status' => 'active',
    ]);

    $this->actingAs($this->agent)
        ->post(route('kora-point.sale.payment', $reservation->uuid), [
            'method' => 'cash',
            'amount' => 15000,
            'cash_received_amount' => 15000,
            'payer_name' => 'Kokou Lome',
            'payer_phone' => '+22890112233',
            'idempotency_key' => (string) Str::uuid(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas('kora_point_sales', [
        'point_id' => $this->point->getKey(),
        'gross_amount' => 15000,
    ]);
});

it('allows partner agent to record mobile money sale across regional operators without agency account', function (): void {
    $networks = ['wave_ci', 'tmoney_tg', 'mtn_momo'];

    $setup = createAgentDeparture();

    foreach ($networks as $network) {
        $reservation = Reservation::query()->create([
            'created_by_user_id' => $this->agent->getKey(),
            'origin_kora_point_id' => $this->point->getKey(),
            'selling_agency_id' => $this->point->agency_id,
            'channel' => 'counter',
            'status' => 'held',
            'total_amount' => 10000,
            'currency' => 'XOF',
            'total_snapshot' => ['passenger_allocations' => [10000]],
            'held_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $reservation->segments()->create([
            'departure_id' => $setup['departure']->id,
            'seat_id' => $setup['seat']->id,
            'origin_stop_id' => $setup['origin']->id,
            'destination_stop_id' => $setup['destination']->id,
            'origin_stop_order' => 1,
            'destination_stop_order' => 2,
            'leg_order' => 1,
            'passenger_index' => 0,
            'direction' => 'outbound',
            'status' => 'held',
        ]);

        $reservation->passengers()->create([
            'first_name' => 'Aya',
            'last_name' => 'Abidjan',
            'phone' => '+22507000001',
            'passenger_index' => 0,
            'status' => 'active',
        ]);

        $this->actingAs($this->agent)
            ->post(route('kora-point.sale.payment', $reservation->uuid), [
                'method' => 'mobile_money',
                'amount' => 10000,
                'mobile_money_network' => $network,
                'agency_payment_account_uuid' => null,
                'payer_phone' => '+22507000001',
                'payer_name' => 'Aya Abidjan',
                'provider_reference' => 'TXN-'.$network.'-1234',
                'verified_manually' => true,
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('payments', [
            'reservation_id' => $reservation->getKey(),
            'method' => 'mobile_money',
            'provider' => $network,
            'provider_reference' => 'TXN-'.$network.'-1234',
            'agency_payment_account_id' => null,
            'cash_session_id' => null,
        ]);
    }
});
