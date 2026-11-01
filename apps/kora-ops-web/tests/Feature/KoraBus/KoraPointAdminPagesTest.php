<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
    Notification::fake();
    $this->commercial = User::factory()->create();
    $this->commercial->assignRole('commercial_director');
    $this->finance = User::factory()->create();
    $this->finance->assignRole('daf');
});

it('renders network points page with points and organizations props for admin', function (): void {
    $point = KoraPoint::factory()->create([
        'commission_basis_points' => 300,
        'ceiling_amount' => 500000,
        'settlement_calendar' => ['frequency' => 'weekly', 'weekday' => 1, 'time' => '18:00', 'timezone' => 'Africa/Porto-Novo'],
    ]);
    $org = KoraPointOrganization::factory()->create(['name' => 'La Poste']);

    $this->actingAs($this->commercial)
        ->get(route('kora-points'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/index')
            ->has('points.data', 1)
            ->where('points.data.0.uuid', $point->uuid)
            ->has('organizations', 1)
            ->where('organizations.0.uuid', $org->uuid)
            ->has('defaults')
        );
});

it('returns 403 on network points page for unauthorized user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('kora-points'))
        ->assertForbidden();
});

it('renders organizations page with contracts and subpoints props', function (): void {
    $org = KoraPointOrganization::factory()->create(['name' => 'Organisation Centrale']);
    $point = KoraPoint::factory()->create(['organization_id' => $org->getKey()]);

    $this->actingAs($this->commercial)
        ->get(route('kora-points.organizations.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/organizations/index')
            ->has('organizations.data', 1)
            ->where('organizations.data.0.uuid', $org->uuid)
            ->has('subpoints', 1)
            ->where('subpoints.0.uuid', $point->uuid)
        );
});

it('renders finance reconciliation cockpit with settlements and refunds for finance user', function (): void {
    $point = KoraPoint::factory()->create([
        'commission_basis_points' => 300,
        'ceiling_amount' => 100000,
        'settlement_calendar' => ['frequency' => 'weekly', 'weekday' => 5, 'time' => '17:00', 'timezone' => 'Africa/Lome'],
    ]);
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->assignRole('kora_point_agent');

    $sale = DB::transaction(function () use ($point, $agent): KoraPointSale {
        $reservation = Reservation::query()->create([
            'created_by_user_id' => $agent->getKey(),
            'payer_user_id' => User::factory()->create()->getKey(),
            'selling_agency_id' => $point->agency_id,
            'channel' => 'counter',
            'status' => 'paid',
            'total_amount' => 15000,
            'currency' => 'XOF',
            'total_snapshot' => ['passenger_allocations' => [15000]],
        ]);
        $reservation->passengers()->create(['first_name' => 'Paul', 'last_name' => 'Finance', 'passenger_index' => 0, 'status' => 'active']);
        $payment = Payment::query()->create([
            'reservation_id' => $reservation->getKey(),
            'method' => 'cash',
            'provider' => 'counter',
            'status' => 'confirmed',
            'amount' => 15000,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
            'payer_phone' => '+22990000002',
            'confirmed_at' => now(),
        ]);

        return app(RecordKoraPointSaleAction::class)->execute($reservation, $payment, $agent);
    });

    $remittance = KoraPointRemittance::query()->create([
        'declared_by' => $agent->getKey(),
        'status' => 'submitted',
        'amount' => 15000,
        'method' => 'bank_transfer',
        'reference' => 'VIR-TEST-001',
        'submitted_at' => now(),
    ]);
    $remittance->lines()->create(['sale_id' => $sale->getKey(), 'amount' => 15000]);

    $refund = KoraPointRefund::query()->create([
        'sale_id' => $sale->getKey(),
        'requested_by' => $agent->getKey(),
        'status' => 'requested',
        'amount' => 15000,
        'reason' => 'Annulation client',
    ]);

    $this->actingAs($this->finance)
        ->get(route('kora-points.finance'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/finance/index')
            ->has('settlements', 1)
            ->where('settlements.0.uuid', $remittance->uuid)
            ->has('refunds', 1)
            ->where('refunds.0.uuid', $refund->uuid)
            ->has('stats')
            ->where('stats.totalToReconcile', 15000)
            ->where('stats.refundsInArbitration', 1)
        );
});

it('returns 403 on finance cockpit for user without kora-points.finance permission', function (): void {
    $this->actingAs($this->commercial)
        ->get(route('kora-points.finance'))
        ->assertForbidden();
});
