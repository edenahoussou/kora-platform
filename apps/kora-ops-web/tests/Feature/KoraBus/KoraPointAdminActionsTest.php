<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

function createTestPointAndSale(int $amount = 20000): array
{
    $point = KoraPoint::factory()->create([
        'commission_basis_points' => 300,
        'ceiling_amount' => 500000,
        'settlement_calendar' => ['frequency' => 'weekly', 'weekday' => 5, 'time' => '17:00', 'timezone' => 'Africa/Lome'],
    ]);
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->assignRole('kora_point_agent');

    $sale = DB::transaction(function () use ($point, $agent, $amount): KoraPointSale {
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
        $reservation->passengers()->create(['first_name' => 'Jean', 'last_name' => 'Voyageur', 'passenger_index' => 0, 'status' => 'active']);
        $payment = Payment::query()->create([
            'reservation_id' => $reservation->getKey(),
            'method' => 'cash',
            'provider' => 'counter',
            'status' => 'confirmed',
            'amount' => $amount,
            'currency' => 'XOF',
            'idempotency_key' => (string) Str::uuid(),
            'payer_phone' => '+22997001122',
            'confirmed_at' => now(),
        ]);

        return app(RecordKoraPointSaleAction::class)->execute($reservation, $payment, $agent);
    });

    return [$point, $agent, $sale];
}

it('allows admin to create a new point via wizard with auto-generated agency code', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Point Relais Akpakpa',
            'city' => 'Cotonou',
            'responsible_name' => 'Jean Dossou',
            'phone' => '+22997123456',
            'commission_basis_points' => 350,
            'ceiling_amount' => 800000,
            'settlement_calendar' => 'weekly',
        ])
        ->assertRedirect();

    $point = KoraPoint::query()->where('responsible_name', 'Jean Dossou')->firstOrFail();
    expect($point->agency->code)->toStartWith('KP-COT-')
        ->and($point->commission_basis_points)->toBe(350)
        ->and($point->ceiling_amount)->toBe(800000)
        ->and($point->settlement_calendar['frequency'])->toBe('weekly')
        ->and($point->status)->toBe('active');
});

it('allows admin to attach a point to an organization and set custom terms', function (): void {
    $org = KoraPointOrganization::factory()->create([
        'name' => 'La Poste du Bénin SA',
        'commission_basis_points' => 300,
        'ceiling_amount' => 5000000,
    ]);

    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Bureau Poste Ganhi',
            'city' => 'Cotonou',
            'responsible_name' => 'Marcellin Houndéto',
            'phone' => '+22997102030',
            'organization_uuid' => $org->uuid,
            'commission_basis_points' => 280,
            'ceiling_amount' => 1200000,
        ])
        ->assertRedirect();

    $point = KoraPoint::query()->where('responsible_name', 'Marcellin Houndéto')->firstOrFail();
    expect($point->organization_id)->toBe($org->getKey())
        ->and($point->commission_basis_points)->toBe(280)
        ->and($point->ceiling_amount)->toBe(1200000);
});

it('allows admin to override financial terms for a specific point', function (): void {
    $point = KoraPoint::factory()->create(['commission_basis_points' => 300]);

    $this->actingAs($this->commercial)
        ->patch(route('kora-points.terms', $point->uuid), [
            'commission_basis_points' => 400,
            'ceiling_amount' => 1500000,
        ])
        ->assertRedirect();

    $point->refresh();
    expect($point->commission_basis_points)->toBe(400)
        ->and($point->ceiling_amount)->toBe(1500000);
});

it('allows finance officer to reconcile a remittance with bank value date and exact amount', function (): void {
    [$point, $agent, $sale] = createTestPointAndSale(25000);

    $remittanceService = app(KoraPointRemittanceService::class);
    $remittance = $remittanceService->create($agent, [$sale->uuid], 'bank_transfer', 'VIR-ECO-2026-99');
    app(KoraPointDocumentService::class)->store($agent, $remittance, ['kind' => 'proof', 'title' => 'Bordereau'], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $remittanceService->submit($agent, $remittance);

    $this->actingAs($this->finance)
        ->post(route('kora-points.remittances.receive', $remittance->uuid), [
            'received_amount' => $remittance->amount,
            'note' => 'Virement bancaire conforme reçu le 31/10.',
            'reject' => false,
        ])
        ->assertRedirect();

    $remittance->refresh();
    $sale->refresh();

    expect($remittance->status)->toBe('validated')
        ->and($remittance->received_amount)->toBe($remittance->amount)
        ->and($remittance->validated_by)->toBe($this->finance->getKey())
        ->and($sale->status)->toBe('settled');
});

it('allows finance officer to flag discrepancy and mark remittance as reconciliation', function (): void {
    [$point, $agent, $sale] = createTestPointAndSale(30000);

    $remittanceService = app(KoraPointRemittanceService::class);
    $remittance = $remittanceService->create($agent, [$sale->uuid], 'bank_transfer', 'VIR-BOA-2026-11');
    app(KoraPointDocumentService::class)->store($agent, $remittance, ['kind' => 'proof', 'title' => 'Bordereau'], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $remittanceService->submit($agent, $remittance);

    $this->actingAs($this->finance)
        ->post(route('kora-points.remittances.receive', $remittance->uuid), [
            'received_amount' => 28000,
            'note' => 'Écart constaté de 2 000 FCFA sur avis de crédit.',
            'reject' => false,
        ])
        ->assertRedirect();

    $remittance->refresh();
    $sale->refresh();

    expect($remittance->status)->toBe('reconciliation')
        ->and($remittance->received_amount)->toBe(28000)
        ->and($sale->status)->not->toBe('settled');
});

it('allows finance officer to reject a remittance with justification', function (): void {
    [$point, $agent, $sale] = createTestPointAndSale(10000);

    $remittanceService = app(KoraPointRemittanceService::class);
    $remittance = $remittanceService->create($agent, [$sale->uuid], 'bank_deposit', 'DEP-ECO-99');
    app(KoraPointDocumentService::class)->store($agent, $remittance, ['kind' => 'proof', 'title' => 'Bordereau'], UploadedFile::fake()->create('recu.pdf', 10, 'application/pdf'));
    $remittanceService->submit($agent, $remittance);

    $this->actingAs($this->finance)
        ->post(route('kora-points.remittances.receive', $remittance->uuid), [
            'received_amount' => 0,
            'reject' => true,
            'note' => 'Bordereau illisible, merci de transmettre le duplicata.',
        ])
        ->assertRedirect();

    $remittance->refresh();
    expect($remittance->status)->toBe('rejected')
        ->and($remittance->note)->toBe('Bordereau illisible, merci de transmettre le duplicata.');
});

it('allows finance officer to authorize and execute a refund', function (): void {
    [$point, $agent, $sale] = createTestPointAndSale(20000);

    $refund = KoraPointRefund::query()->create([
        'sale_id' => $sale->getKey(),
        'requested_by' => $agent->getKey(),
        'status' => 'requested',
        'amount' => 20000,
        'reason' => 'Passager empêché',
    ]);

    $this->actingAs($this->finance)
        ->post(route('kora-points.refunds.authorize', $refund->uuid))
        ->assertRedirect();

    $refund->refresh();
    expect($refund->status)->toBe('authorized')
        ->and($refund->authorized_by)->toBe($this->finance->getKey());
});
