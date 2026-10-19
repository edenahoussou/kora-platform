<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Data\ExpenseStatus;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Profitability\Data\ProfitabilityAxis;
use App\Modules\Profitability\Services\ProfitabilityReport;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 12:00:00', RevenuePeriod::TIMEZONE));
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function profitabilityScenario(): array
{
    $agency = Agency::factory()->create(['name' => 'Agence Étoile']);
    [$line, $origin, $destination] = lineWithStops();
    $line->forceFill(['distance_km' => 100])->save();
    $bus = Bus::query()->create([
        'uuid' => (string) Str::uuid(),
        'registration_number' => 'BJ 9254 RB',
        'brand' => 'Toyota',
        'model' => 'Coaster',
        'seat_count' => 50,
        'status' => 'active',
    ]);
    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-10-05',
        'departure_time' => '08:00',
        'status' => 'departed',
    ]);
    $seats = collect(['2A', '2B'])->map(fn (string $code): Seat => Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => $code,
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]));

    $reservation = Reservation::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'KBR-TEST0001',
        'selling_agency_id' => $agency->id,
        'status' => 'paid',
        'total_amount' => 20_000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'total_snapshot' => [
            'passenger_allocations' => [10_000, 10_000],
            'legs' => [[
                'leg_order' => 1,
                'departure_uuid' => $departure->uuid,
                'final_amount' => 10_000,
            ]],
        ],
    ]);

    foreach ($seats as $index => $seat) {
        ReservationSegment::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'leg_order' => 1,
            'passenger_index' => $index,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => 1,
            'destination_stop_order' => 2,
            'status' => 'ticketed',
        ]);
    }

    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'provider' => 'test',
        'idempotency_key' => 'payment-'.fake()->uuid(),
        'amount' => 20_000,
        'currency' => 'XOF',
        'method' => 'cash',
        'status' => 'confirmed',
        'confirmed_at' => CarbonImmutable::parse('2026-10-02 09:00:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    $refund = PaymentRefund::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $payment->id,
        'amount' => 2_000,
        'currency' => 'XOF',
        'destination' => 'wallet_cash',
        'status' => 'completed',
        'reference' => 'RFD-TEST0001',
        'idempotency_key' => 'refund-'.fake()->uuid(),
    ]);
    $refund->forceFill(['created_at' => CarbonImmutable::parse('2026-10-08 10:00:00', RevenuePeriod::TIMEZONE)->utc()])->saveQuietly();

    $futureDeparture = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => '2026-11-05',
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);
    $futureReservation = Reservation::query()->create([
        'uuid' => (string) Str::uuid(),
        'reference' => 'KBR-TEST0002',
        'selling_agency_id' => $agency->id,
        'status' => 'paid',
        'total_amount' => 7_000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'total_snapshot' => ['passenger_allocations' => [7_000]],
    ]);
    ReservationSegment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $futureReservation->id,
        'leg_order' => 1,
        'passenger_index' => 0,
        'departure_id' => $futureDeparture->id,
        'seat_id' => $seats->first()->id,
        'origin_stop_id' => $origin->id,
        'destination_stop_id' => $destination->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 2,
        'status' => 'ticketed',
    ]);
    Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $futureReservation->id,
        'provider' => 'test',
        'idempotency_key' => 'payment-'.fake()->uuid(),
        'amount' => 7_000,
        'currency' => 'XOF',
        'method' => 'cash',
        'status' => 'confirmed',
        'confirmed_at' => CarbonImmutable::parse('2026-10-03 09:00:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    $creator = User::factory()->create();
    $fuel = ExpenseCategory::factory()->create(['code' => 'fuel', 'label' => 'Carburant']);
    $insurance = ExpenseCategory::factory()->create(['code' => 'insurance', 'label' => 'Assurance']);
    $supplies = ExpenseCategory::factory()->create(['code' => 'agency_supplies', 'label' => 'Fournitures agence']);

    Expense::factory()->for($agency)->approved()->create([
        'expense_category_id' => $fuel->id,
        'bus_id' => $bus->id,
        'amount' => 5_000,
        'occurred_at' => CarbonImmutable::parse('2026-10-06 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
        'created_by' => $creator->id,
    ]);
    Expense::factory()->for($agency)->approved()->create([
        'expense_category_id' => $insurance->id,
        'bus_id' => $bus->id,
        'amount' => 3_000,
        'occurred_at' => CarbonImmutable::parse('2026-10-07 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
        'created_by' => $creator->id,
    ]);
    Expense::factory()->for($agency)->approved()->create([
        'expense_category_id' => $supplies->id,
        'amount' => 2_000,
        'occurred_at' => CarbonImmutable::parse('2026-10-08 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
        'created_by' => $creator->id,
    ]);
    Expense::factory()->for($agency)->create([
        'expense_category_id' => $fuel->id,
        'status' => ExpenseStatus::Pending,
        'bus_id' => $bus->id,
        'amount' => 9_000,
        'occurred_at' => CarbonImmutable::parse('2026-10-09 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
        'created_by' => $creator->id,
    ]);

    return compact('agency', 'line', 'bus', 'departure', 'reservation', 'payment');
}

it('allows only finance, operations and commercial leadership to view and export profitability', function (): void {
    foreach (['daf', 'general_management', 'operations_director', 'commercial_director', 'system_admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('profitability'))->assertSuccessful();
        $this->actingAs($user)->get(route('profitability.export'))->assertSuccessful();
    }

    foreach (['agency_manager', 'agency_agent', 'conductor'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('profitability'))->assertForbidden();
        $this->actingAs($user)->get(route('profitability.export'))->assertForbidden();
    }
});

it('builds service revenue, attributed costs, TCO and break-even for a departure', function (): void {
    $scenario = profitabilityScenario();
    $report = app(ProfitabilityReport::class)->build(RevenuePeriod::fromFilters(['period' => 'this_month']), ProfitabilityAxis::Departure);

    expect($report['hero']['revenueNet'])->toBe(18_000)
        ->and($report['hero']['costAssigned'])->toBe(8_000)
        ->and($report['hero']['costUnassigned'])->toBe(2_000)
        ->and($report['hero']['result'])->toBe(8_000)
        ->and($report['hero']['marginPercent'])->toBe(44.4)
        ->and($report['hero']['coveragePercent'])->toBe(80.0)
        ->and($report['kpis']['tco'])->toBe(8_000)
        ->and($report['kpis']['costPerKm'])->toBe(80.0)
        ->and($report['reconciliation']['cashNet'])->toBe(25_000)
        ->and($report['reconciliation']['serviceNet'])->toBe(18_000)
        ->and($report['reconciliation']['timingGap'])->toBe(7_000);

    $card = collect($report['cards'])->firstWhere('key', $scenario['departure']->uuid);
    expect($card)->not->toBeNull()
        ->and($card['revenueNet'])->toBe(18_000)
        ->and($card['costTotal'])->toBe(8_000)
        ->and($card['contribution'])->toBe(10_000)
        ->and($card['seatsSold'])->toBe(2)
        ->and($card['capacity'])->toBe(50)
        ->and($card['breakEvenSeats'])->toBe(1)
        ->and($card['breakEvenStatus'])->toBe('atteint')
        ->and($card['distanceSource'])->toBe('estimated')
        ->and($card['km'])->toBe(100.0);

    $pnl = collect($report['pnl'])->keyBy('key');
    expect($pnl['ticket_revenue']['amount'])->toBe(18_000)
        ->and($pnl['cost_fuel']['amount'])->toBe(-5_000)
        ->and($pnl['cost_insurance']['amount'])->toBe(-3_000)
        ->and($pnl['cost_agency_supplies']['amount'])->toBe(-2_000)
        ->and($pnl['parcel_revenue']['status'])->toBe('out_of_scope')
        ->and($pnl['parcel_revenue']['amount'])->toBeNull()
        ->and($pnl['hr_casskai']['status'])->toBe('pending')
        ->and($pnl['result']['amount'])->toBe(8_000);

    $tco = collect($report['tco'])->firstWhere('label', 'BJ 9254 RB');
    expect($tco['tco'])->toBe(8_000)
        ->and($tco['km'])->toBe(100.0)
        ->and($tco['costPerKm'])->toBe(80.0)
        ->and($tco['revenuePerSeat'])->toBe(9_000)
        ->and($tco['resultPerSeat'])->toBe(5_000);
});

it('renders the profitability page with the selected axis and drawer detail', function (): void {
    profitabilityScenario();
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $this->actingAs($daf)
        ->get(route('profitability', ['period' => 'this_month', 'axis' => 'bus']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('profitability')
            ->where('axis', 'bus')
            ->has('cards', 1)
            ->where('cards.0.label', 'BJ 9254 RB')
            ->where('cards.0.revenueNet', 18_000)
            ->where('cards.0.costTotal', 8_000)
            ->where('hero.result', 8_000)
            ->where('period.preset', 'this_month')
            ->where('detail', null));

    $departureUuid = Departure::query()->firstOrFail()->uuid;
    $this->actingAs($daf)
        ->get(route('profitability', ['period' => 'this_month', 'axis' => 'departure', 'detail' => $departureUuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('detail.card.key', $departureUuid)
            ->where('detail.revenue.net', 18_000)
            ->where('detail.costs.total', 8_000)
            ->where('detail.categories.fuel', 5_000)
            ->has('detail.departures', 1));
});

it('exports a multi-sheet workbook and audits the action', function (): void {
    profitabilityScenario();
    $daf = User::factory()->create();
    $daf->assignRole('daf');

    $response = $this->actingAs($daf)->get(route('profitability.export', [
        'period' => 'this_month',
        'axis' => 'bus',
    ]));

    $content = $response->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'profitability-xlsx-');
    expect($path)->not->toBeFalse();
    file_put_contents($path, $content);
    $workbook = IOFactory::load($path);
    $sheets = $workbook->getSheetNames();

    expect($sheets)->toContain('Synthèse', 'Axes', 'P&L', 'TCO', 'Contrôles', 'Départs')
        ->and($workbook->getSheetByName('Synthèse')?->getCell('B6')->getValue())->toBe(18_000)
        ->and(Activity::query()->where('event', 'profitability_exported')->exists())->toBeTrue();

    $workbook->disconnectWorksheets();
    unlink($path);
});

it('computes quarter presets for the report period', function (): void {
    $thisQuarter = RevenuePeriod::fromFilters(['period' => 'this_quarter']);
    $lastQuarter = RevenuePeriod::fromFilters(['period' => 'last_quarter']);

    expect($thisQuarter->localStart->toDateString())->toBe('2026-10-01')
        ->and($thisQuarter->localEnd->toDateString())->toBe('2026-10-15')
        ->and($lastQuarter->localStart->toDateString())->toBe('2026-07-01')
        ->and($lastQuarter->localEnd->toDateString())->toBe('2026-09-30')
        ->and($lastQuarter->comparisonPeriod()->localEnd->toDateString())->toBe('2026-06-30');
});

it('aggregates agency revenue and unassigned agency costs with a drawer detail', function (): void {
    profitabilityScenario();
    $report = app(ProfitabilityReport::class)->build(
        RevenuePeriod::fromFilters(['period' => 'this_month']),
        ProfitabilityAxis::Agency,
    );

    $card = collect($report['cards'])->firstWhere('label', 'Agence Étoile');
    expect($card)->not->toBeNull()
        ->and($card['revenueNet'])->toBe(18_000)
        ->and($card['costTotal'])->toBe(2_000)
        ->and($card['contribution'])->toBe(16_000);

    $detail = app(ProfitabilityReport::class)->detail(
        RevenuePeriod::fromFilters(['period' => 'this_month']),
        ProfitabilityAxis::Agency,
        (string) $card['key'],
    );

    expect($detail['revenue']['net'])->toBe(18_000)
        ->and($detail['costs']['total'])->toBe(2_000)
        ->and($detail['categories']['agency_supplies'])->toBe(2_000)
        ->and($detail['departures'])->toHaveCount(1);
});

it('aggregates line and period axes without losing attributed costs', function (): void {
    $scenario = profitabilityScenario();
    $service = app(ProfitabilityReport::class);
    $period = RevenuePeriod::fromFilters(['period' => 'this_month']);

    $lineReport = $service->build($period, ProfitabilityAxis::Line);
    $lineCard = collect($lineReport['cards'])->firstWhere('label', $scenario['line']->code);
    expect($lineCard)->not->toBeNull()
        ->and($lineCard['revenueNet'])->toBe(18_000)
        ->and($lineCard['costTotal'])->toBe(8_000)
        ->and($lineCard['contribution'])->toBe(10_000);

    $periodReport = $service->build($period, ProfitabilityAxis::Period);
    $periodCards = collect($periodReport['cards'])->keyBy('key');
    expect($periodReport['cards'])->toHaveCount(2)
        ->and($periodCards['current']['revenueNet'])->toBe(18_000)
        ->and($periodCards['current']['costTotal'])->toBe(10_000)
        ->and($periodCards['previous']['revenueNet'])->toBe(0);
});
