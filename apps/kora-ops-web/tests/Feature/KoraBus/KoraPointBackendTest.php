<?php

use App\Models\User;
use App\Modules\Booking\Actions\CancelReservationAction;
use App\Modules\Booking\Models\ItineraryQuote;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\ServiceClass;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Jobs\SendKoraPointReminderJob;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use App\Modules\KoraPoints\Services\KoraPointLedgerService;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
use App\Modules\KoraPoints\Services\KoraPointReadService;
use App\Modules\KoraPoints\Services\KoraPointRefundService;
use App\Modules\KoraPoints\Services\KoraPointRemittanceService;
use App\Modules\KoraPoints\Services\KoraPointTermsService;
use App\Modules\Notifications\Jobs\SendOpsInboxPushJob;
use App\Modules\Payments\Actions\ConfirmPaymentAction;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Services\DatabaseSettingsCoordinator;
use App\Modules\Settings\Settings\OperationsSettings;
use App\Notifications\KoraPointSettlementOverdue;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'UTC'));
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
    $this->notificationDispatcher = Notification::getFacadeRoot();
    Notification::fake();
    $this->commercial = User::factory()->create();
    $this->commercial->assignRole('commercial_director');
    $this->finance = User::factory()->create();
    $this->finance->assignRole('daf');
});

function kpCalendar(): array
{
    return ['frequency' => 'weekly', 'weekday' => 5, 'time' => '17:00', 'timezone' => 'Africa/Lome'];
}

function kpPoint(array $overrides = []): KoraPoint
{
    return KoraPoint::factory()->create($overrides + ['commission_basis_points' => 300, 'ceiling_amount' => 100000, 'settlement_calendar' => kpCalendar()]);
}

function kpAgent(KoraPoint $point): User
{
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->assignRole('kora_point_agent');

    return $agent;
}

function kpSale(KoraPoint $point, ?User $agent = null, int $amount = 10000): KoraPointSale
{
    $agent ??= kpAgent($point);

    return DB::transaction(function () use ($point, $agent, $amount): KoraPointSale {
        $reservation = Reservation::query()->create(['created_by_user_id' => $agent->getKey(), 'payer_user_id' => User::factory()->create()->getKey(), 'selling_agency_id' => $point->agency_id, 'channel' => 'counter', 'status' => 'paid', 'total_amount' => $amount, 'currency' => 'XOF', 'total_snapshot' => ['passenger_allocations' => [intdiv($amount, 2), $amount - intdiv($amount, 2)]]]);
        $reservation->passengers()->create(['first_name' => 'Ada', 'last_name' => 'Test', 'passenger_index' => 0, 'status' => 'active']);
        $reservation->passengers()->create(['first_name' => 'Ben', 'last_name' => 'Test', 'passenger_index' => 1, 'status' => 'active']);
        $payment = Payment::query()->create(['reservation_id' => $reservation->getKey(), 'method' => 'cash', 'provider' => 'counter', 'status' => 'confirmed', 'amount' => $amount, 'currency' => 'XOF', 'idempotency_key' => (string) Str::uuid(), 'payer_phone' => '+22890000001', 'confirmed_at' => now()]);

        return app(RecordKoraPointSaleAction::class)->execute($reservation, $payment, $agent);
    });
}

function kpProof(User $actor, mixed $subject): void
{
    app(KoraPointDocumentService::class)->store($actor, $subject, ['kind' => 'other', 'title' => 'Justificatif'], UploadedFile::fake()->create('preuve.pdf', 10, 'application/pdf'));
}

function kpRemit(User $actor, KoraPointSale $sale, User $finance): void
{
    $service = app(KoraPointRemittanceService::class);
    $remittance = $service->create($actor, [$sale->uuid], 'bank_transfer', 'BANK-'.Str::random(8));
    kpProof($actor, $remittance);
    $service->submit($actor, $remittance);
    $service->receive($finance, $remittance, $remittance->amount);
}

it('creates standalone points through web routes with a web account and optional GPS and documents', function (): void {
    $this->actingAs($this->commercial)->post(route('kora-points.store'), ['code' => 'KP-1', 'name' => 'Boutique Lomé', 'city' => 'Lomé', 'country' => 'TG', 'responsible_name' => 'Afi', 'phone' => '+22890000000', 'email' => 'afi.point@example.com'])->assertRedirect();
    $point = KoraPoint::query()->sole();
    expect($point->organization_id)->toBeNull()->and($point->latitude)->toBeNull()->and($point->agency->kind)->toBe('partner');
    expect(User::query()->where('email', 'afi.point@example.com')->whereHas('roles', fn ($query) => $query->where('name', 'kora_point_agent'))->exists())->toBeTrue();
});

it('creates an organization with auto generated code composed from structure name', function (): void {
    $this->actingAs($this->commercial)->post(route('kora-points.organizations.store'), [
        'name' => 'La Poste du Bénin',
        'country' => 'CI',
        'registration_identifier' => 'REG-CI-2026-01',
        'tax_identifier' => 'TAX-CI-2026-01',
        'phone' => '+22921300000',
        'address' => 'Ganhi, Cotonou',
        'commission_basis_points' => 350,
        'ceiling_amount' => 2500000,
        'settlement_calendar' => ['frequency' => 'weekly', 'time' => '18:00', 'timezone' => 'Africa/Porto-Novo', 'weekday' => 1],
    ])->assertRedirect();

    $org = KoraPointOrganization::query()->where('name', 'La Poste du Bénin')->sole();
    expect($org->code)->toBe('LPDB-01')
        ->and($org->phone)->toBe('+22921300000')
        ->and($org->country)->toBe('CI')
        ->and($org->registration_identifier)->toBe('REG-CI-2026-01')
        ->and($org->tax_identifier)->toBe('TAX-CI-2026-01')
        ->and($org->address)->toBe('Ganhi, Cotonou')
        ->and($org->commission_basis_points)->toBe(350)
        ->and($org->ceiling_amount)->toBe(2500000)
        ->and($org->settlement_calendar['frequency'])->toBe('weekly');
});

it('requires supported country and complete terms before creating an organization', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('kora-points.organizations.store'), [
            'name' => 'Organisation sans conditions',
            'country' => 'GH',
        ])
        ->assertSessionHasErrors([
            'country',
            'commission_basis_points',
            'ceiling_amount',
            'settlement_calendar',
        ]);

    expect(KoraPointOrganization::query()->where('name', 'Organisation sans conditions')->exists())->toBeFalse();
});

it('stores a partner logo and lets attached points inherit it', function (): void {
    Storage::fake('public');

    $this->actingAs($this->commercial)->post(route('kora-points.organizations.store'), [
        'name' => 'Coris Bank',
        'country' => 'BJ',
        'logo' => UploadedFile::fake()->image('logo.png', 64, 64),
        'commission_basis_points' => 300,
        'ceiling_amount' => 500000,
        'settlement_calendar' => kpCalendar(),
    ])->assertRedirect();

    $org = KoraPointOrganization::query()->where('name', 'Coris Bank')->sole();
    expect($org->getMedia('logo'))->toHaveCount(1)
        ->and($org->getFirstMediaUrl('logo', 'thumb'))->not->toBeEmpty();

    $point = kpPoint(['organization_id' => $org->id]);
    $view = app(KoraPointReadService::class)->point($point->load(['organization.media', 'media']), $this->commercial);
    expect($view['organization_logo_url'])->toBe($org->getFirstMediaUrl('logo', 'thumb'))
        ->and($view['logo_url'])->toBeNull();

    $this->actingAs($this->commercial)->put(route('kora-points.organizations.update', $org->uuid), [
        'name' => 'Coris Bank',
        'country' => 'BJ',
        'commission_basis_points' => $org->commission_basis_points,
        'ceiling_amount' => $org->ceiling_amount,
        'settlement_calendar' => $org->settlement_calendar,
        'remove_logo' => '1',
    ])->assertRedirect();
    expect($org->refresh()->getMedia('logo'))->toHaveCount(0);
});

it('creates an organization and its points atomically and rolls back invalid batches', function (): void {
    $point = ['code' => 'POST-1', 'name' => 'Bureau central', 'city' => 'Lomé', 'department' => 'Maritime', 'country' => 'TG', 'responsible_name' => 'Afi', 'email' => 'afi.post@example.com', 'ceiling_amount' => 100000];
    $organizationTerms = ['country' => 'TG', 'commission_basis_points' => 300, 'ceiling_amount' => 500000, 'settlement_calendar' => kpCalendar()];
    $this->actingAs($this->commercial)->post(route('kora-points.organizations.store'), ['code' => 'POST', 'name' => 'La Poste', ...$organizationTerms, 'points' => [$point, $point]])->assertSessionHasErrors('points.0.code');
    expect(KoraPointOrganization::query()->count())->toBe(0);
    $org = app(KoraPointManagementService::class)->saveOrganization($this->commercial, ['code' => 'POST', 'name' => 'La Poste', ...$organizationTerms, 'points' => [$point, array_replace($point, ['code' => 'POST-2', 'email' => 'afi.post.two@example.com'])]]);
    expect($org->points)->toHaveCount(2)->and($org->points->every(fn (KoraPoint $point): bool => $point->commission_basis_points === null && $point->settlement_calendar === null))->toBeTrue();
});

it('inherits terms without treating zero commission as inheritance and freezes sales', function (): void {
    $org = KoraPointOrganization::factory()->create(['commission_basis_points' => 400, 'ceiling_amount' => 50000, 'settlement_calendar' => kpCalendar()]);
    $point = KoraPoint::factory()->create(['organization_id' => $org->id, 'commission_basis_points' => null, 'settlement_calendar' => null, 'ceiling_amount' => 50000]);
    $terms = app(KoraPointTermsService::class);
    expect($terms->resolve($point)['commission_basis_points'])->toBe(400);
    expect(fn () => app(KoraPointManagementService::class)->updateTerms($this->commercial, $point, ['commission_basis_points' => 0]))->toThrow(ValidationException::class);
    $sale = kpSale($point->refresh());
    app(KoraPointManagementService::class)->updateTerms($this->commercial, $org, ['commission_basis_points' => 900, 'ceiling_amount' => 50000, 'settlement_calendar' => kpCalendar()]);
    expect($sale->refresh()->commission_amount)->toBe(400)->and($sale->terms_snapshot['commission_basis_points'])->toBe(400);
});

it('keeps attached point allocation under organization control for internal administrators', function (): void {
    $organization = KoraPointOrganization::factory()->create([
        'country' => 'BJ',
        'commission_basis_points' => 300,
        'ceiling_amount' => 500000,
        'settlement_calendar' => kpCalendar(),
    ]);
    $point = kpPoint([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 100000,
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);
    $management = app(KoraPointManagementService::class);

    expect(fn () => $management->savePoint($this->commercial, ['ceiling_amount' => 125000], $point))
        ->toThrow(ValidationException::class);
    expect(fn () => $management->updateTerms($this->commercial, $point, ['ceiling_amount' => 125000]))
        ->toThrow(ValidationException::class);

    expect($point->refresh()->ceiling_amount)->toBe(100000);
});

it('updates only point defaults using the existing audited settings lock', function (): void {
    $this->actingAs($this->commercial)->patch(route('kora-points.defaults'), ['commission_basis_points' => 250, 'ceiling_amount' => 25000, 'settlement_calendar' => kpCalendar(), 'grace_days' => 2])->assertRedirect();
    $point = KoraPoint::factory()->create();
    expect(app(KoraPointTermsService::class)->required($point)['commission_basis_points'])->toBe(250)->and(app(OperationsSettings::class)->koraPointGraceDays)->toBe(2);
    expect(fn () => app(DatabaseSettingsCoordinator::class)->updateKoraPointDefaults(['maxLegs' => 10], $this->commercial))->toThrow(ValidationException::class);
    $this->assertDatabaseHas('activity_log', ['description' => 'settings.updated']);
});

it('blocks sales when required terms are missing', function (): void {
    expect(fn () => kpSale(KoraPoint::factory()->create()))->toThrow(ValidationException::class);
    expect(Payment::query()->count())->toBe(0);
});

it('calculates the commission half up once and never resets monthly debt', function (): void {
    $point = kpPoint(['commission_basis_points' => 5000]);
    $agent = kpAgent($point);
    $sale = kpSale($point, $agent, 101);
    DB::transaction(fn () => app(RecordKoraPointSaleAction::class)->execute($sale->reservation, $sale->payment, $agent));
    expect($sale->commission_amount)->toBe(51)->and($sale->refresh()->outstanding_amount)->toBe(50)->and(KoraPointEntry::query()->count())->toBe(1);
    $this->travelTo(now()->addMonth());
    expect(app(KoraPointLedgerService::class)->balance($point))->toBe(50);
});

it('admits the exact ceiling and rolls back a sale that exceeds it', function (): void {
    $point = kpPoint(['ceiling_amount' => 9700]);
    kpSale($point);
    expect(fn () => kpSale($point))->toThrow(ValidationException::class);
    expect(KoraPointSale::query()->count())->toBe(1)->and(Payment::query()->count())->toBe(1);
});

it('isolates agents and supervisors and returns explicit Inertia props without numeric identifiers', function (): void {
    $org = KoraPointOrganization::factory()->create();
    $point = kpPoint(['organization_id' => $org->id]);
    $other = kpPoint();
    $agent = kpAgent($point);
    $supervisor = User::factory()->create();
    app(KoraPointManagementService::class)->assign(tap(User::factory()->create(), fn ($user) => $user->assignRole('system_admin')), $supervisor, $org, 'kora_point_supervisor');
    $this->actingAs($agent)->get(route('kora-points.show', $other->uuid))->assertForbidden();
    $this->actingAs($agent)->get(route('kora-points.show', $point->uuid))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canViewNetworkContext', false)
            ->where('permissions.canManageMembers', false)
            ->where('organization.global_ceiling_amount', null)
            ->where('organization.allocated_ceiling_amount', null)
            ->has('members.data', 0));
    $this->actingAs($supervisor->refresh())->get(route('kora-partner.points.show', $point->uuid))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('kora-points/partner/point')->where('point.uuid', $point->uuid)->missing('point.id')->missing('point.agency_id'));
    $this->actingAs($supervisor->refresh())->get(route('kora-points.show', $other->uuid))->assertForbidden();
    $this->actingAs($supervisor->refresh())->post(route('kora-points.remittances.store'), [])->assertSessionHasErrors(['sale_uuids', 'method', 'reference']);
    $this->actingAs($agent)->get(route('administration.settings.operations'))->assertForbidden();
});

it('renders an autonomous point detail with its own terms, documents and only point agents', function (): void {
    $point = kpPoint(['commission_basis_points' => 500, 'ceiling_amount' => 80000]);
    $activeAgent = kpAgent($point);
    $activeAgent->update(['name' => 'Agent Actif']);
    $sale = kpSale($point, $activeAgent, 10000);
    $refund = KoraPointRefund::query()->create([
        'sale_id' => $sale->getKey(),
        'requested_by' => $activeAgent->getKey(),
        'status' => 'confirmed',
        'amount' => 1000,
        'reason' => 'Annulation',
    ]);
    $sale->update(['commission_reversed' => 100]);
    KoraPointEntry::query()->create([
        'point_id' => $point->getKey(),
        'sale_id' => $sale->getKey(),
        'actor_id' => $this->commercial->getKey(),
        'event_key' => 'refund:'.$refund->uuid,
        'type' => 'refund',
        'amount' => -900,
        'commission_delta' => -100,
    ]);
    $suspendedAgent = kpAgent($point);
    $suspendedAgent->update(['name' => 'Agent Suspendu', 'status' => 'suspended']);
    kpAgent(kpPoint());
    $organization = KoraPointOrganization::factory()->create();
    foreach (['kora_point_supervisor', 'kora_point_treasurer'] as $role) {
        $organizationMember = User::factory()->create([
            'agency_id' => $point->agency_id,
            'kora_point_organization_id' => $organization->getKey(),
        ]);
        $organizationMember->assignRole($role);
    }
    $document = KoraPartnerDocument::query()->create([
        'point_id' => $point->getKey(),
        'uploaded_by' => $this->commercial->getKey(),
        'kind' => 'agreement',
        'title' => 'Convention du Point',
    ]);

    $this->actingAs($this->commercial)->get(route('kora-points.show', $point->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/show')
            ->where('point.uuid', $point->uuid)
            ->where('point.gross_sales_amount', $sale->gross_amount)
            ->where('point.terms.sources.commission_basis_points', 'point')
            ->where('organization', null)
            ->has('members.data', 2)
            ->where('members.data.0.uuid', $activeAgent->uuid)
            ->where('members.data.0.status', 'active')
            ->where('members.data.1.uuid', $suspendedAgent->uuid)
            ->where('members.data.1.status', 'suspended')
            ->where('permissions.canManageMembers', false)
            ->where('refunds.data.0.uuid', $refund->uuid)
            ->where('refunds.data.0.commission_reversed', 100)
            ->where('documents.0.uuid', $document->uuid)
            ->where('documents.0.source', 'point')
            ->missing('point.id')
            ->missing('members.data.0.id')
            ->missing('documents.0.point_id'));
});

it('shows inherited organization conditions, shared documents and point allocation on an attached point', function (): void {
    $organization = KoraPointOrganization::factory()->create([
        'commission_basis_points' => 450,
        'ceiling_amount' => 500000,
        'settlement_calendar' => kpCalendar(),
    ]);
    $point = kpPoint([
        'organization_id' => $organization->getKey(),
        'commission_basis_points' => null,
        'settlement_calendar' => null,
        'ceiling_amount' => 100000,
    ]);
    kpPoint(['organization_id' => $organization->getKey(), 'ceiling_amount' => 50000]);
    $document = KoraPartnerDocument::query()->create([
        'organization_id' => $organization->getKey(),
        'uploaded_by' => $this->commercial->getKey(),
        'kind' => 'agreement',
        'title' => 'Convention cadre',
    ]);

    $this->actingAs($this->commercial)->get(route('kora-points.show', $point->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/show')
            ->where('point.terms.sources.commission_basis_points', 'organization')
            ->where('point.commission_rate_bps', 450)
            ->where('organization.uuid', $organization->uuid)
            ->where('organization.global_ceiling_amount', 500000)
            ->where('organization.allocated_ceiling_amount', 150000)
            ->where('organization.point_allocation_amount', 100000)
            ->where('permissions.canManageMembers', false)
            ->has('members.data', 0)
            ->where('documents.0.uuid', $document->uuid)
            ->where('documents.0.source', 'organization')
            ->missing('organization.id')
            ->missing('documents.0.organization_id'));
});

it('attributes grouped remittance lines to the point while preserving the group total', function (): void {
    $organization = KoraPointOrganization::factory()->create(['ceiling_amount' => 500000]);
    $point = kpPoint(['organization_id' => $organization->getKey(), 'ceiling_amount' => 100000]);
    $otherPoint = kpPoint(['organization_id' => $organization->getKey(), 'ceiling_amount' => 100000]);
    $pointSale = kpSale($point, null, 10000);
    $otherSale = kpSale($otherPoint, null, 20000);
    $supervisor = User::factory()->create(['kora_point_organization_id' => $organization->getKey()]);
    $supervisor->assignRole('kora_point_supervisor');
    $remittance = app(KoraPointRemittanceService::class)->create(
        $supervisor,
        [$pointSale->uuid, $otherSale->uuid],
        'bank_transfer',
        'VIR-RESEAU-001',
    );

    $this->actingAs($this->commercial)->get(route('kora-points.show', $point->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('remittances.data.0.uuid', $remittance->uuid)
            ->where('remittances.data.0.point_amount', $pointSale->outstanding_amount)
            ->where('remittances.data.0.group_amount', $pointSale->outstanding_amount + $otherSale->outstanding_amount)
            ->where('remittances.data.0.sales_count', 2)
            ->missing('remittances.data.0.id'));
});

it('aggregates the partner workspace by organization and filters each view by point', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $pointOne = kpPoint(['organization_id' => $organization->getKey()]);
    $pointTwo = kpPoint(['organization_id' => $organization->getKey()]);
    $otherPoint = kpPoint();
    kpSale($pointOne, null, 10000);
    kpSale($pointTwo, null, 20000);
    kpSale($otherPoint, null, 50000);

    $supervisor = User::factory()->create(['kora_point_organization_id' => $organization->getKey()]);
    $supervisor->assignRole('kora_point_supervisor');

    $this->actingAs($supervisor)->get(route('kora-partner.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/dashboard')
            ->where('metrics.today_sales_amount', 30000)
            ->where('metrics.today_commission_amount', 900)
            ->where('health.credit_limit', 200000)
            ->where('health.global_credit_limit', (int) $organization->ceiling_amount)
            ->has('points', 2)
            ->has('priorities'));

    $this->get(route('kora-partner.sales', ['point_uuid' => $pointOne->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/sales')
            ->where('selected_point_uuid', $pointOne->uuid)
            ->where('sales.total', 1)
            ->where('sales.data.0.point_uuid', $pointOne->uuid));

    $this->get(route('kora-partner.commissions', ['period' => 'all', 'point_uuid' => $pointTwo->uuid]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/commissions')
            ->where('period', 'all')
            ->where('selected_point_uuid', $pointTwo->uuid)
            ->where('totals.gross', 20000)
            ->where('totals.net', 600)
            ->where('sales.total', 1));

    $this->get(route('kora-partner.sales', ['point_uuid' => $otherPoint->uuid]))->assertNotFound();
});

it('reuses the autonomous versement flow for the whole organization network', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $pointOne = kpPoint(['organization_id' => $organization->getKey()]);
    $pointTwo = kpPoint(['organization_id' => $organization->getKey()]);
    $saleOne = kpSale($pointOne, null, 10000);
    $saleTwo = kpSale($pointTwo, null, 20000);
    $otherSale = kpSale(kpPoint(), null, 50000);
    $otherAgent = User::query()->findOrFail($otherSale->seller_id);
    app(KoraPointRemittanceService::class)->create($otherAgent, [$otherSale->uuid], 'bank_transfer', 'HORS-RESEAU');
    $supervisor = User::factory()->create(['kora_point_organization_id' => $organization->getKey()]);
    $supervisor->assignRole('kora_point_supervisor');

    $this->actingAs($supervisor)
        ->get(route('kora-points.remittances.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/remittances')
            ->where('organization_name', $organization->name)
            ->where('health.current_exposure', $saleOne->outstanding_amount + $saleTwo->outstanding_amount)
            ->has('settlements', 0));

    $this->getJson(route('kora-points.remittances.preview', ['from' => '2026-10-30', 'to' => '2026-10-31']))
        ->assertOk()
        ->assertJsonPath('data.tickets_count', 2)
        ->assertJsonPath('data.net_amount', $saleOne->outstanding_amount + $saleTwo->outstanding_amount);

    $this->post(route('kora-points.remittances.declare'), [
        'sale_uuids' => [$saleOne->uuid, $saleTwo->uuid],
        'method' => 'bank_transfer',
        'reference' => 'VIR-ORG-2026-17',
        'proof' => UploadedFile::fake()->create('preuve-reseau.pdf', 30, 'application/pdf'),
    ])->assertRedirect(route('kora-points.remittances.index'));

    $remittance = KoraPointRemittance::query()->where('reference', 'VIR-ORG-2026-17')->sole();
    expect($remittance->organization_id)->toBe($organization->getKey())
        ->and($remittance->amount)->toBe($saleOne->outstanding_amount + $saleTwo->outstanding_amount)
        ->and($remittance->lines()->count())->toBe(2)
        ->and($remittance->declared_by)->toBe($supervisor->getKey())
        ->and($remittance->status)->toBe('submitted');

    expect(KoraPartnerDocument::query()->where('remittance_id', $remittance->getKey())->where('kind', 'remittance_proof')->exists())->toBeTrue();
    expect($saleOne->refresh()->status)->not->toBe('settled')
        ->and($saleTwo->refresh()->status)->not->toBe('settled');

    $this->get(route('kora-points.remittances.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/remittances')
            ->where('settlements.0.status', 'DECLARED')
            ->where('settlements.0.sales_count', 2)
            ->has('settlements', 1));
});

it('prevents direct partner and payer cancellation of point sales', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    expect(fn () => app(CancelReservationAction::class)->cancel($sale->reservation, $agent))->toThrow(AuthorizationException::class);
    expect(fn () => app(CancelReservationAction::class)->cancel($sale->reservation, $sale->reservation->payer))->toThrow(AuthorizationException::class);
});

it('reduces debt only after finance validates receipt and handles differences without releasing the ceiling', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRemittanceService::class);
    $remittance = $service->create($agent, [$sale->uuid], 'bank_transfer', 'R1');
    kpProof($agent, $remittance);
    $service->submit($agent, $remittance);
    expect($sale->refresh()->outstanding_amount)->toBe(9700);
    $service->receive($this->finance, $remittance, 9600);
    expect($sale->refresh()->outstanding_amount)->toBe(9700)->and($remittance->refresh()->status)->toBe('reconciliation');
    $service->receive($this->finance, $remittance, 9700);
    $service->receive($this->finance, $remittance, 9700);
    expect($sale->refresh()->outstanding_amount)->toBe(0)->and($sale->entries()->count())->toBe(2);
});

it('allows one treasury payment allocated across bureaux and prevents overlapping claims', function (): void {
    $org = KoraPointOrganization::factory()->create();
    $sale1 = kpSale(kpPoint(['organization_id' => $org->id]));
    $sale2 = kpSale(kpPoint(['organization_id' => $org->id]));
    $treasurer = User::factory()->create();
    $treasurer->forceFill(['kora_point_organization_id' => $org->id])->save();
    $treasurer->assignRole('kora_point_treasurer');
    $service = app(KoraPointRemittanceService::class);
    $batch = $service->create($treasurer, [$sale1->uuid, $sale2->uuid], 'bank_transfer', 'GROUP');
    expect($batch->amount)->toBe(19400);
    expect(fn () => $service->create($treasurer, [$sale1->uuid], 'bank_transfer', 'DUP'))->toThrow(ValidationException::class);
    kpProof($treasurer, $batch);
    $service->submit($treasurer, $batch);
    $service->receive($this->finance, $batch, 19400);
    expect($sale1->refresh()->outstanding_amount)->toBe(0)->and($sale2->refresh()->outstanding_amount)->toBe(0);
});

it('disallows self validation and unlocks sales only when a batch is rejected', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $agent->givePermissionTo('kora-points.finance');
    $service = app(KoraPointRemittanceService::class);
    $batch = $service->create($agent, [$sale->uuid], 'cash', 'R1');
    expect(fn () => $service->receive($agent, $batch, 9700))->toThrow(AuthorizationException::class);
    expect(fn () => app(KoraPointRefundService::class)->request($agent, $sale, 'Annulation'))->toThrow(ValidationException::class);
    $service->receive($this->finance, $batch, 0, true, 'Déclaration retirée avant envoi.');
    expect(app(KoraPointRefundService::class)->request($agent, $sale, 'Annulation')->status)->toBe('requested');
});

it('requires proof before submitting a remittance', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $batch = app(KoraPointRemittanceService::class)->create($agent, [$sale->uuid], 'cash', 'R1');
    expect(fn () => app(KoraPointRemittanceService::class)->submit($agent, $batch))->toThrow(ValidationException::class);
});

it('lets the point refund before remittance and only adjusts debt on confirmed restitution', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRefundService::class);
    $refund = $service->request($agent, $sale, 'Voyage annulé');
    $service->authorize($this->finance, $refund);
    expect($refund->refresh()->executor)->toBe('point')->and($sale->refresh()->outstanding_amount)->toBe(9700);
    kpProof($agent, $refund);
    $service->execute($agent, $refund, ['method' => 'cash', 'reference' => 'CASH-REF', 'beneficiary' => '+22890000001']);
    expect($sale->refresh()->outstanding_amount)->toBe(9700);
    $service->confirm($this->finance, $refund);
    $service->confirm($this->finance, $refund);
    expect($sale->refresh()->outstanding_amount)->toBe(0)->and($sale->commission_reversed)->toBe(300)->and($refund->refresh()->paymentRefund->status->value)->toBe('completed')->and($refund->paymentRefund->destination->value)->toBe('partner_manual');
});

it('lets KORA refund after receipt and recovers the retained commission in the next remittance', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    kpRemit($agent, $sale, $this->finance);
    $service = app(KoraPointRefundService::class);
    $refund = $service->request($agent, $sale, 'Voyage annulé');
    $service->authorize($this->finance, $refund);
    expect($refund->refresh()->executor)->toBe('kora');
    kpProof($this->finance, $refund);
    $service->execute($this->finance, $refund, ['method' => 'cash', 'reference' => 'KORA-REF', 'beneficiary' => '+22890000001']);
    $service->confirm($this->finance, $refund);
    expect($sale->refresh()->outstanding_amount)->toBe(300);
    kpRemit($agent, $sale->refresh(), $this->finance);
    expect($sale->refresh()->outstanding_amount)->toBe(0);
});

it('handles sequential passenger refunds using frozen allocations without exceeding the original commission', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRefundService::class);
    foreach ($sale->reservation->passengers as $index => $passenger) {
        $refund = $service->request($agent, $sale, 'Annulation passager', $passenger->uuid);
        $service->authorize($this->finance, $refund);
        expect($refund->refresh()->amount)->toBe(5000);
        kpProof($agent, $refund);
        $service->execute($agent, $refund, ['method' => 'cash', 'reference' => 'PART-'.$index, 'beneficiary' => '+22890000001']);
        $service->confirm($this->finance, $refund);
    }
    expect($sale->refresh()->refunded_amount)->toBe(10000)->and($sale->commission_reversed)->toBe(300)->and($sale->outstanding_amount)->toBe(0);
});

it('blocks overdue sales after grace without undoing manual suspension on payment', function (): void {
    $point = kpPoint();
    $sale = kpSale($point);
    $agent = User::query()->findOrFail($sale->seller_id);
    $this->travelTo($sale->due_at->addSecond());
    expect(fn () => kpSale($point))->toThrow(ValidationException::class);
    app(KoraPointManagementService::class)->changeStatus($this->commercial, $point, 'suspended');
    kpRemit($agent, $sale, $this->finance);
    expect(fn () => kpSale($point->refresh()))->toThrow(ValidationException::class);
    expect($point->refresh()->status)->toBe('suspended');
});

it('deduplicates reminders without posting financial entries', function (): void {
    $sale = kpSale(kpPoint());
    $this->travelTo($sale->due_at->addSecond());
    Queue::fake([SendKoraPointReminderJob::class]);
    $this->artisan('kora-points:check-settlements')->assertSuccessful();
    $this->artisan('kora-points:check-settlements')->assertSuccessful();
    expect(DB::table('kora_point_reminders')->count())->toBe(1)->and(KoraPointEntry::query()->count())->toBe(1);
    Queue::assertPushed(SendKoraPointReminderJob::class);
    $job = new SendKoraPointReminderJob(DB::table('kora_point_reminders')->value('id'));
    $job->handle();
    $job->handle();
    Notification::assertSentToTimes(User::query()->findOrFail($sale->seller_id), KoraPointSettlementOverdue::class, 1);
});

it('prevents reparenting an indebted point and prevents cross point private downloads', function (): void {
    $point = kpPoint();
    $sale = kpSale($point);
    $org = KoraPointOrganization::factory()->create();
    expect(fn () => app(KoraPointManagementService::class)->savePoint($this->commercial, ['organization_uuid' => $org->uuid], $point))->toThrow(ValidationException::class);
    $doc = app(KoraPointDocumentService::class)->store($this->commercial, $point, ['kind' => 'agreement', 'title' => 'Convention'], UploadedFile::fake()->create('contrat.pdf', 10, 'application/pdf'));
    $this->actingAs(kpAgent(kpPoint()))->get(route('kora-points.documents.download', $doc->uuid))->assertForbidden();
    $this->actingAs(User::query()->findOrFail($sale->seller_id))->get(route('kora-points.documents.download', $doc->uuid))->assertDownload();
});

it('protects ledger entries against direct SQL updates', function (): void {
    kpSale(kpPoint());
    expect(fn () => DB::table('kora_point_entries')->update(['amount' => 0]))->toThrow(QueryException::class);
});

it('records partner debt atomically with the existing counter payment and rejects ceiling overflow without cash side effects', function (): void {
    $this->travelTo(Carbon::parse('2026-08-19 07:00:00'));
    $scenario = kpCounterScenario();
    $scenario['agency']->update(['kind' => 'partner']);
    $point = kpPoint(['agency_id' => $scenario['agency']->id, 'ceiling_amount' => 10669]);
    $scenario['agent']->syncRoles(['kora_point_agent']);
    $reservation = kpCounterReservation($this, $scenario);
    kpCounterCashSession($scenario);
    $payload = ['method' => 'cash', 'amount' => 11000, 'cash_received_amount' => 11000, 'payer_name' => 'Payeur', 'payer_phone' => '+22890000001', 'idempotency_key' => (string) Str::uuid()];
    expect(fn () => app(RecordCounterSalePaymentAction::class)->execute($reservation, $scenario['agent'], $payload))->toThrow(ValidationException::class);
    expect(Payment::query()->count())->toBe(0)->and(CashMovement::query()->count())->toBe(0)->and(CashSession::query()->sole()->total_cash)->toBe(0);
    app(KoraPointManagementService::class)->updateTerms($this->commercial, $point, ['ceiling_amount' => 10670]);
    $result = app(RecordCounterSalePaymentAction::class)->execute($reservation, $scenario['agent'], $payload);
    app(RecordCounterSalePaymentAction::class)->execute($reservation, $scenario['agent'], $payload);
    expect(KoraPointSale::query()->sole()->commission_amount)->toBe(330)->and(app(KoraPointLedgerService::class)->balance($point))->toBe(10670)->and(CashMovement::query()->count())->toBe(0)->and($result['reservation']->origin_kora_point_id)->toBe($point->id);
});

it('rejects incomplete calendars without altering the effective calendar', function (): void {
    $point = kpPoint();
    $this->actingAs($this->commercial)->patch(route('kora-points.terms', $point->uuid), ['settlement_calendar' => ['frequency' => 'weekly']])->assertSessionHasErrors(['settlement_calendar.time', 'settlement_calendar.weekday']);
    expect($point->refresh()->settlement_calendar)->toBe(kpCalendar());
});

it('preserves original organization attribution after a debt free transfer', function (): void {
    $old = KoraPointOrganization::factory()->create();
    $new = KoraPointOrganization::factory()->create();
    $point = kpPoint(['organization_id' => $old->id]);
    $sale = kpSale($point);
    $treasurer = User::factory()->create();
    $treasurer->forceFill(['kora_point_organization_id' => $old->id])->save();
    $treasurer->assignRole('kora_point_treasurer');
    kpRemit($treasurer, $sale, $this->finance);
    app(KoraPointManagementService::class)->savePoint($this->commercial, ['organization_uuid' => $new->uuid], $point);
    $newSupervisor = User::factory()->create();
    $newSupervisor->forceFill(['kora_point_organization_id' => $new->id])->save();
    $newSupervisor->assignRole('kora_point_supervisor');
    expect(app(KoraPointAccess::class)->sales($treasurer)->count())->toBe(1)
        ->and(app(KoraPointAccess::class)->sales($newSupervisor)->count())->toBe(0)
        ->and($sale->refresh()->organization_id)->toBe($old->id);
    $this->actingAs($newSupervisor)->get(route('kora-partner.points.show', $point->uuid))->assertInertia(fn (Assert $page) => $page->component('kora-points/partner/point')->where('sales.total', 0)->where('point.gross_sales_amount', 0));
});

it('rejects cross-country transfers and transfers with an unsettled point balance', function (): void {
    $old = KoraPointOrganization::factory()->create(['country' => 'BJ']);
    $sameCountryTarget = KoraPointOrganization::factory()->create(['country' => 'BJ']);
    $differentCountryTarget = KoraPointOrganization::factory()->create(['country' => 'TG']);
    $point = kpPoint([
        'organization_id' => $old->getKey(),
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);

    expect(fn () => app(KoraPointManagementService::class)->savePoint(
        $this->commercial,
        ['organization_uuid' => $differentCountryTarget->uuid],
        $point,
    ))->toThrow(ValidationException::class);

    kpSale($point);
    expect(fn () => app(KoraPointManagementService::class)->savePoint(
        $this->commercial,
        ['organization_uuid' => $sameCountryTarget->uuid],
        $point,
    ))->toThrow(ValidationException::class);

    expect($point->refresh()->organization_id)->toBe($old->getKey());
});

it('refunds only the remaining paid amount when a full cancellation follows a partial refund', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRefundService::class);
    $passenger = $sale->reservation->passengers->first();
    foreach ([$passenger->uuid, null] as $index => $passengerUuid) {
        $refund = $service->request($agent, $sale, 'Annulation', $passengerUuid);
        $service->authorize($this->finance, $refund);
        expect($refund->refresh()->amount)->toBe(5000);
        kpProof($agent, $refund);
        $service->execute($agent, $refund, ['method' => 'cash', 'reference' => 'REMAINING-'.$index, 'beneficiary' => '+22890000001']);
        $service->confirm($this->finance, $refund);
    }
    expect($sale->refresh()->refunded_amount)->toBe(10000)->and($sale->outstanding_amount)->toBe(0);
});

it('requires proof and the original payer and method for an authorized restitution', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRefundService::class);
    $refund = $service->request($agent, $sale, 'Annulation');
    $service->authorize($this->finance, $refund);
    $data = ['method' => 'cash', 'reference' => 'REF'];
    expect(fn () => $service->execute($agent, $refund, $data))->toThrow(ValidationException::class);
    kpProof($agent, $refund);
    expect(fn () => $service->execute($agent, $refund, array_replace($data, ['method' => 'mobile_money'])))->toThrow(ValidationException::class);
    expect($refund->refresh()->status)->toBe('authorized')->and($sale->refresh()->outstanding_amount)->toBe(9700);
    $service->execute($agent, $refund, $data);
    expect($refund->refresh()->beneficiary)->toBe('+22890000001');
});

it('uses configured grace days while retaining the original sale due date', function (): void {
    app(DatabaseSettingsCoordinator::class)->updateKoraPointDefaults(['koraPointGraceDays' => 2], $this->commercial);
    $point = kpPoint();
    $sale = kpSale($point);
    $due = $sale->due_at;
    $this->travelTo($due->addDay());
    kpSale($point);
    $this->travelTo($due->addDays(3));
    expect(fn () => kpSale($point))->toThrow(ValidationException::class);
    expect($sale->refresh()->due_at->equalTo($due))->toBeTrue();
});

it('blocks stale reservation origin after an agent is reassigned to another point', function (): void {
    $point = kpPoint();
    $agent = kpAgent($point);
    $sale = kpSale($point, $agent);
    $other = kpPoint();
    $agent->update(['agency_id' => $other->agency_id]);
    expect(fn () => app(RecordKoraPointSaleAction::class)->execute($sale->reservation, $sale->payment, $agent))->toThrow(ValidationException::class);
});

it('opens the sales catalog without exposing company credit and denies partner API access', function (): void {
    $agent = kpAgent(kpPoint());
    $this->actingAs($agent)->get(route('sale'))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('sale')->where('companyAccounts', []));
    $token = $agent->createToken('partner-web-only')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/v1/health')->assertForbidden();
});

it('delivers actionable reminders through the existing web inbox without opening a partner mobile API', function (): void {
    Notification::swap($this->notificationDispatcher);
    Queue::fake([SendKoraPointReminderJob::class, SendOpsInboxPushJob::class]);
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $this->travelTo($sale->due_at->addSecond());
    $this->artisan('kora-points:check-settlements')->assertSuccessful();
    $job = new SendKoraPointReminderJob(DB::table('kora_point_reminders')->where('sale_id', $sale->id)->value('id'));
    $job->handle();
    $job->handle();
    $this->actingAs($agent)->getJson(route('api.v1.notifications.index'))->assertSuccessful()->assertJsonPath('data.0.type', 'kora_point_settlement_overdue')->assertJsonPath('data.0.action_url', route('kora-points.show', $sale->point->uuid));
    expect($agent->notifications()->count())->toBe(1);
    $other = kpAgent(kpPoint());
    $this->actingAs($other)->getJson(route('api.v1.notifications.index'))->assertSuccessful()->assertJsonCount(0, 'data');
    $this->actingAs($agent)->withToken($agent->createToken('no-partner-mobile')->plainTextToken)->getJson(route('api.v1.notifications.index'))->assertForbidden();
});

it('lets only one partner point hold a contested seat across bureaux', function (): void {
    $this->travelTo(Carbon::parse('2026-08-19 07:00:00'));
    $scenario = kpCounterScenario();
    $scenario['agency']->update(['kind' => 'partner']);
    kpPoint(['agency_id' => $scenario['agency']->id]);
    $scenario['agent']->syncRoles(['kora_point_agent']);
    kpCounterReservation($this, $scenario);

    $agencyB = Agency::query()->create(['code' => 'PAY-AG-B', 'name' => 'Bureau B', 'city' => 'Lomé', 'country' => 'TG', 'status' => 'active', 'kind' => 'partner']);
    kpPoint(['agency_id' => $agencyB->id]);
    $agentB = User::factory()->create(['agency_id' => $agencyB->id]);
    $agentB->assignRole('kora_point_agent');

    $context = ['date' => '2026-08-20', 'from' => 'Cotonou', 'to' => 'Abidjan', 'trip_type' => 'one_way'];
    $legContext = ['departure_uuid' => $scenario['departure']->uuid, 'origin_stop_uuid' => $scenario['origin']->uuid, 'destination_stop_uuid' => $scenario['destination']->uuid];
    $this->actingAs($agentB)->post(route('sale.quote'), $context + ['passenger_count' => 1, 'outbound_legs' => [$legContext]]);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    $this->actingAs($agentB)->post(route('sale.hold'), $context + [
        'itinerary_quote_uuid' => $quote->uuid,
        'idempotency_key' => (string) Str::uuid(),
        'passengers' => [['passenger_index' => 0, 'seat_assignments' => [['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seats']['2A']->uuid]]]],
    ])->assertSessionHasErrors('seats');
    expect(Reservation::query()->where('created_by_user_id', $agentB->id)->count())->toBe(0);
});

it('lets finance reject an authorized refund so the sale becomes remittable again', function (): void {
    $sale = kpSale(kpPoint());
    $agent = User::query()->findOrFail($sale->seller_id);
    $service = app(KoraPointRefundService::class);
    $refund = $service->request($agent, $sale, 'Erreur');
    $service->authorize($this->finance, $refund);
    expect(fn () => app(KoraPointRemittanceService::class)->create($agent, [$sale->uuid], 'cash', 'R1'))->toThrow(ValidationException::class);
    $service->authorize($this->finance, $refund, true);
    expect($refund->refresh()->status)->toBe('rejected')->and($refund->paymentRefund->refresh()->status->value)->toBe('failed');
    $remittances = app(KoraPointRemittanceService::class);
    $remittance = $remittances->create($agent, [$sale->uuid], 'cash', 'R1');
    expect($remittance->status)->toBe('draft');
    $remittances->receive($this->finance, $remittance, 0, true);
    $refund2 = $service->request($agent, $sale->refresh(), 'Nouvelle demande');
    $service->authorize($this->finance, $refund2);
    kpProof($agent, $refund2);
    $service->execute($agent, $refund2, ['method' => 'cash', 'reference' => 'X']);
    expect(fn () => $service->authorize($this->finance, $refund2, true))->toThrow(ValidationException::class);
});

it('blocks assigning a user holding an internal role but promotes a passenger', function (): void {
    $point = kpPoint();
    $internal = User::factory()->create();
    $internal->assignRole('agency_agent');
    $admin = tap(User::factory()->create(), fn ($user) => $user->assignRole('system_admin'));
    $management = app(KoraPointManagementService::class);
    expect(fn () => $management->assign($admin, $internal, $point, 'kora_point_agent'))->toThrow(ValidationException::class);
    $passenger = User::factory()->create();
    $passenger->assignRole('passenger');
    $management->assign($admin, $passenger, $point, 'kora_point_agent');
    expect($passenger->refresh()->hasRole('kora_point_agent'))->toBeTrue()->and($passenger->hasRole('passenger'))->toBeFalse();
});

it('tracks sale status across open settled and reopened debt', function (): void {
    $sale = kpSale(kpPoint());
    expect($sale->status)->toBe('open');
    $agent = User::query()->findOrFail($sale->seller_id);
    kpRemit($agent, $sale, $this->finance);
    expect($sale->refresh()->status)->toBe('settled');
    $service = app(KoraPointRefundService::class);
    $refund = $service->request($agent, $sale, 'Annulation');
    $service->authorize($this->finance, $refund);
    kpProof($this->finance, $refund);
    $service->execute($this->finance, $refund, ['method' => 'cash', 'reference' => 'K']);
    $service->confirm($this->finance, $refund);
    expect($sale->refresh()->status)->toBe('reopened')->and($sale->outstanding_amount)->toBe(300);
    kpRemit($agent, $sale->refresh(), $this->finance);
    expect($sale->refresh()->status)->toBe('settled');
    $sale2 = kpSale(kpPoint());
    $agent2 = User::query()->findOrFail($sale2->seller_id);
    $refund2 = $service->request($agent2, $sale2, 'Annulation');
    $service->authorize($this->finance, $refund2);
    kpProof($agent2, $refund2);
    $service->execute($agent2, $refund2, ['method' => 'cash', 'reference' => 'P']);
    $service->confirm($this->finance, $refund2);
    expect($sale2->refresh()->status)->toBe('settled');
});

it('rejects provider confirmation of a partner reservation outside the counter circuit', function (): void {
    $point = kpPoint();
    $agent = kpAgent($point);
    $reservation = Reservation::query()->create(['created_by_user_id' => $agent->getKey(), 'channel' => 'counter', 'status' => 'held', 'total_amount' => 10000, 'currency' => 'XOF']);
    $payment = Payment::query()->create(['reservation_id' => $reservation->getKey(), 'method' => 'mobile_money', 'provider' => 'fedapay', 'status' => 'pending', 'amount' => 10000, 'currency' => 'XOF', 'idempotency_key' => (string) Str::uuid()]);
    expect(fn () => app(ConfirmPaymentAction::class)->handle($payment, 'confirmed'))->toThrow(ValidationException::class);
    expect($payment->refresh()->status->value)->toBe('pending')->and($reservation->refresh()->status->value)->toBe('held')->and(KoraPointSale::query()->count())->toBe(0);
});

it('creates organization members with initial web access and without registration otp', function (): void {
    $admin = tap(User::factory()->create(), fn ($user) => $user->assignRole('system_admin'));

    $org = app(KoraPointManagementService::class)->saveOrganization($admin, [
        'name' => 'La Poste Bénin Test',
        'code' => 'LPB-TEST',
        'country' => 'BJ',
        'phone' => '+22997000000',
        'commission_basis_points' => 300,
        'ceiling_amount' => 1500000,
        'settlement_calendar' => kpCalendar(),
        'members' => [
            [
                'name' => 'Jean Superviseur',
                'email' => 'jean.sup@laposte.bj',
                'phone' => '+22997010203',
                'role' => 'kora_point_supervisor',
            ],
            [
                'name' => 'Marc Tresorier',
                'email' => 'marc.tres@laposte.bj',
                'phone' => '+22997040506',
                'role' => 'kora_point_treasurer',
            ],
        ],
    ]);

    $supervisor = User::query()->where('email', 'jean.sup@laposte.bj')->first();
    expect($supervisor)->not->toBeNull()
        ->and($supervisor->name)->toBe('Jean Superviseur')
        ->and($supervisor->hasRole('kora_point_supervisor'))->toBeTrue()
        ->and($supervisor->kora_point_organization_id)->toBe($org->id)
        ->and($supervisor->must_change_password)->toBeTrue();

    $treasurer = User::query()->where('email', 'marc.tres@laposte.bj')->first();
    expect($treasurer)->not->toBeNull()
        ->and($treasurer->name)->toBe('Marc Tresorier')
        ->and($treasurer->hasRole('kora_point_treasurer'))->toBeTrue()
        ->and($treasurer->kora_point_organization_id)->toBe($org->id)
        ->and($treasurer->must_change_password)->toBeTrue();

    expect(AuthOtpChallenge::query()->where('user_id', $supervisor->id)->count())->toBe(0)
        ->and(AuthOtpChallenge::query()->where('user_id', $treasurer->id)->count())->toBe(0)
        ->and(KoraPointWebAccessInvitation::query()->where('user_id', $supervisor->id)->value('status'))->toBeIn(['pending_delivery', 'sent'])
        ->and(KoraPointWebAccessInvitation::query()->where('user_id', $treasurer->id)->value('status'))->toBeIn(['pending_delivery', 'sent']);
});

it('creates a kora_point_agent user account when saving a point with email', function (): void {
    $admin = tap(User::factory()->create(), fn ($user) => $user->assignRole('system_admin'));

    $point = app(KoraPointManagementService::class)->savePoint($admin, [
        'name' => 'Point Etoile Rouge',
        'city' => 'Cotonou',
        'responsible_name' => 'Aïcha Gérant',
        'phone' => '+22997112233',
        'email' => 'aicha.agent@etoile.bj',
    ]);

    $agent = User::query()->where('email', 'aicha.agent@etoile.bj')->first();
    expect($agent)->not->toBeNull()
        ->and($agent->name)->toBe('Aïcha Gérant')
        ->and($agent->hasRole('kora_point_agent'))->toBeTrue()
        ->and($agent->agency_id)->toBe($point->agency_id)
        ->and($agent->must_change_password)->toBeTrue();

    expect(AuthOtpChallenge::query()->where('user_id', $agent->id)->count())->toBe(0)
        ->and(KoraPointWebAccessInvitation::query()->where('user_id', $agent->id)->value('status'))->toBeIn(['pending_delivery', 'sent']);
});

it('requires an email when creating a standalone point account', function (): void {
    $admin = tap(User::factory()->create(), fn ($user) => $user->assignRole('system_admin'));

    $initialUserCount = User::query()->count();

    expect(fn () => app(KoraPointManagementService::class)->savePoint($admin, [
        'name' => 'Point Sans Compte',
        'city' => 'Porto-Novo',
        'responsible_name' => 'Bio Sans Email',
        'phone' => '+22997445566',
        'email' => null,
    ]))->toThrow(ValidationException::class);

    expect(User::query()->count())->toBe($initialUserCount)
        ->and(KoraPoint::query()->count())->toBe(0);
});

function kpCounterScenario(): array
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

function kpCounterReservation(object $test, array $scenario, string $seatCode = '2A'): Reservation
{
    $context = [
        'date' => '2026-08-20', 'from' => 'Cotonou', 'to' => 'Abidjan',
        'trip_type' => 'one_way',
    ];
    $legContext = [
        'departure_uuid' => $scenario['departure']->uuid,
        'origin_stop_uuid' => $scenario['origin']->uuid,
        'destination_stop_uuid' => $scenario['destination']->uuid,
    ];
    $test->actingAs($scenario['agent'])->post(route('sale.quote'), $context + [
        'passenger_count' => 1,
        'outbound_legs' => [$legContext],
    ]);
    $quote = ItineraryQuote::query()->latest('id')->firstOrFail();
    $test->actingAs($scenario['agent'])->post(route('sale.hold'), $context + [
        'itinerary_quote_uuid' => $quote->uuid,
        'idempotency_key' => (string) Str::uuid(),
        'passengers' => [[
            'passenger_index' => 0,
            'seat_assignments' => [
                ['leg_order' => 1, 'direction' => 'outbound', 'seat_uuid' => $scenario['seats'][$seatCode]->uuid],
            ],
        ]],
    ]);
    $reservation = Reservation::query()->latest('id')->firstOrFail();
    $test->actingAs($scenario['agent'])->post(route('sale.passengers', $reservation), $context + [
        'information_confirmed' => true,
        'passengers' => [[
            'first_name' => 'Aïcha', 'last_name' => 'Hounkpè', 'phone' => '+22997241806', 'email' => 'aicha@example.com',
            'document_type' => 'passport', 'document_number' => 'BJ0427851', 'nationality' => 'BJ', 'birth_date' => '1994-04-18',
            'seat_uuid' => $scenario['seats'][$seatCode]->uuid,
        ]],
    ]);

    return $reservation->fresh();
}

/** @param array{agent: User, agency: Agency} $scenario @param list<AgencyPaymentAccount> $accounts */
function kpCounterCashSession(array $scenario, int $openingCash = 0, array $accounts = []): CashSession
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
