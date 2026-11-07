<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            'department' => 'Littoral',
            'responsible_name' => 'Jean Dossou',
            'phone' => '+22997123456',
            'email' => 'jean.dossou.point@example.com',
            'commission_basis_points' => 350,
            'ceiling_amount' => 800000,
            'settlement_calendar' => 'weekly',
        ])
        ->assertRedirect();

    $point = KoraPoint::query()->where('responsible_name', 'Jean Dossou')->firstOrFail();
    expect($point->agency->code)->toStartWith('KP-COT-')
        ->and($point->agency->department)->toBe('Littoral')
        ->and($point->commission_basis_points)->toBe(350)
        ->and($point->ceiling_amount)->toBe(800000)
        ->and($point->settlement_calendar['frequency'])->toBe('weekly')
        ->and($point->status)->toBe('active');
});

it('creates an organization point with its own positive allocation and inherited terms', function (): void {
    $org = KoraPointOrganization::factory()->create([
        'name' => 'La Poste du Bénin SA',
        'commission_basis_points' => 300,
        'ceiling_amount' => 5000000,
    ]);

    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Bureau Poste Ganhi',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Marcellin Houndéto',
            'email' => 'marcellin.point@example.com',
            'organization_uuid' => $org->uuid,
            'ceiling_amount' => 1200000,
        ])
        ->assertRedirect();

    $point = KoraPoint::query()->where('responsible_name', 'Marcellin Houndéto')->firstOrFail();
    expect($point->organization_id)->toBe($org->getKey())
        ->and($point->agency->country)->toBe($org->country)
        ->and($point->commission_basis_points)->toBeNull()
        ->and($point->settlement_calendar)->toBeNull()
        ->and($point->ceiling_amount)->toBe(1200000)
        ->and(app(KoraPointTermsService::class)->resolve($point)['commission_basis_points'])->toBe(300)
        ->and(app(KoraPointTermsService::class)->resolve($point)['settlement_calendar'])->toBe($org->settlement_calendar);
});

it('rejects organization allocations above the global ceiling and below point exposure', function (): void {
    $organization = KoraPointOrganization::factory()->create(['ceiling_amount' => 500000]);
    [$first] = createTestPointAndSale(20000);
    $first->forceFill([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 350000,
    ])->save();

    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Bureau au-delà du plafond',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Agent nouveau',
            'email' => 'agent.limit@example.com',
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 200000,
        ])
        ->assertSessionHasErrors('ceiling_amount');

    expect(KoraPoint::query()->where('organization_id', $organization->getKey())->count())->toBe(1);

    $this->actingAs($this->commercial)
        ->patch(route('kora-points.terms', $first->uuid), [
            'ceiling_amount' => 5000,
        ])
        ->assertSessionHasErrors('ceiling_amount');

    $this->actingAs($this->commercial)
        ->put(route('kora-points.update', $first->uuid), [
            'name' => $first->agency->name,
            'city' => $first->agency->city,
            'country' => $first->agency->country,
            'code' => $first->agency->code,
            'responsible_name' => $first->responsible_name,
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 5000,
        ])
        ->assertSessionHasErrors('ceiling_amount');
});

it('keeps attached point allocations read-only for KORA admins and does not release unavailable capacity', function (): void {
    $organization = KoraPointOrganization::factory()->create([
        'ceiling_amount' => 4_500_000,
    ]);
    $point = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 4_500_000,
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);

    $this->actingAs($this->commercial)
        ->put(route('kora-points.update', $point->uuid), [
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'code' => $point->agency->code,
            'responsible_name' => $point->responsible_name,
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 2_500_000,
        ])
        ->assertSessionHasErrors([
            'ceiling_amount' => 'L’allocation d’un Point rattaché se gère depuis l’espace de son organisation.',
        ]);

    expect($point->refresh()->ceiling_amount)->toBe(4_500_000);

    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Point reliquat GREEN PAY',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Agent reliquat',
            'email' => 'agent.reliquat@example.com',
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 2_000_000,
        ])
        ->assertSessionHasErrors([
            'ceiling_amount' => 'La somme des allocations dépasserait le plafond global de l’organisation.',
        ]);

    expect(KoraPoint::query()->where('organization_id', $organization->getKey())->count())->toBe(1)
        ->and(KoraPoint::query()->where('organization_id', $organization->getKey())->sum('ceiling_amount'))
        ->toBe(4_500_000);
});

it('shows a field validation error when an attached point allocation is zero', function (): void {
    $organization = KoraPointOrganization::factory()->create([
        'ceiling_amount' => 4_500_000,
    ]);
    $point = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 4_500_000,
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);

    $this->actingAs($this->commercial)
        ->put(route('kora-points.update', $point->uuid), [
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'code' => $point->agency->code,
            'responsible_name' => $point->responsible_name,
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 0,
        ])
        ->assertSessionHasErrors([
            'ceiling_amount' => 'L’allocation d’un Point rattaché se gère depuis l’espace de son organisation.',
        ]);

    expect($point->refresh()->ceiling_amount)->toBe(4_500_000);
});

it('refuses logo uploads and removal attempts for an organization attached point', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $point = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);
    $payload = [
        'name' => $point->agency->name,
        'city' => $point->agency->city,
        'country' => $point->agency->country,
        'code' => $point->agency->code,
        'responsible_name' => $point->responsible_name,
        'organization_uuid' => $organization->uuid,
        'ceiling_amount' => $point->ceiling_amount,
    ];

    $this->actingAs($this->commercial)
        ->put(route('kora-points.update', $point->uuid), $payload + [
            'logo' => UploadedFile::fake()->image('point-logo.png'),
        ])
        ->assertSessionHasErrors('logo');

    $this->actingAs($this->commercial)
        ->put(route('kora-points.update', $point->uuid), $payload + [
            'remove_logo' => '1',
        ])
        ->assertSessionHasErrors('logo');

    expect($point->getMedia('logo'))->toBeEmpty();
});

it('prevents document uploads on an organization attached point', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $point = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);

    expect(fn () => app(KoraPointDocumentService::class)->store(
        $this->commercial,
        $point,
        ['kind' => 'other', 'title' => 'Document point'],
        UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'),
    ))->toThrow(ValidationException::class);
});

it('uploads organization documents on its profile and autonomous point documents on the point', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $this->actingAs($this->commercial)
        ->post(route('kora-points.organizations.documents', $organization->uuid), [
            'kind' => 'agreement',
            'title' => 'Convention cadre',
            'file' => UploadedFile::fake()->create('convention.pdf', 20, 'application/pdf'),
        ])
        ->assertRedirect();

    $point = KoraPoint::factory()->create();
    $this->actingAs($this->commercial)
        ->post(route('kora-points.documents', $point->uuid), [
            'kind' => 'other',
            'title' => 'Justificatif du Point',
            'file' => UploadedFile::fake()->create('justificatif.pdf', 20, 'application/pdf'),
        ])
        ->assertRedirect();

    expect($organization->documents()->where('title', 'Convention cadre')->exists())->toBeTrue()
        ->and(KoraPartnerDocument::query()->where('point_id', $point->getKey())->where('title', 'Justificatif du Point')->exists())->toBeTrue();
});

it('keeps suspended allocations reserved and releases a closed point allocation only after it is settled', function (): void {
    $organization = KoraPointOrganization::factory()->create(['ceiling_amount' => 500000]);
    $point = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 300000,
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);

    app(KoraPointManagementService::class)->changeStatus(
        $this->commercial,
        $point,
        'suspended',
    );

    expect(fn () => app(KoraPointManagementService::class)->savePoint(
        $this->commercial,
        [
            'name' => 'Point au-delà du disponible',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Agent disponible',
            'email' => 'agent.available@example.com',
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 300000,
        ],
    ))->toThrow(ValidationException::class);

    app(KoraPointManagementService::class)->changeStatus(
        $this->commercial,
        $point,
        'closed',
    );

    $newPoint = app(KoraPointManagementService::class)->savePoint(
        $this->commercial,
        [
            'name' => 'Point après clôture soldée',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Agent clôture',
            'email' => 'agent.closed@example.com',
            'organization_uuid' => $organization->uuid,
            'ceiling_amount' => 300000,
        ],
    );

    expect($newPoint->organization_id)->toBe($organization->getKey());
});

it('rejects a global ceiling below reserved point allocations and a point from another country', function (): void {
    $organization = KoraPointOrganization::factory()->create(['ceiling_amount' => 500000]);
    KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 300000,
    ]);

    $this->actingAs($this->commercial)
        ->patch(route('kora-points.organizations.terms', $organization->uuid), [
            'commission_basis_points' => 300,
            'ceiling_amount' => 200000,
            'settlement_calendar' => [
                'frequency' => 'weekly',
                'weekday' => 1,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ],
        ])
        ->assertSessionHasErrors('ceiling_amount');

    $togoOrganization = KoraPointOrganization::factory()->create([
        'country' => 'TG',
        'ceiling_amount' => 500000,
    ]);
    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Point mauvais pays',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Agent pays incompatible',
            'email' => 'agent.country@example.com',
            'organization_uuid' => $togoOrganization->uuid,
            'ceiling_amount' => 100000,
        ])
        ->assertSessionHasErrors('country');

    expect(KoraPoint::query()->where('responsible_name', 'Agent pays incompatible')->exists())->toBeFalse();
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
