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
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Jobs\SendKoraPointReminderJob;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\KoraPoints\Services\KoraPointAccess;
use App\Modules\KoraPoints\Services\KoraPointDocumentService;
use App\Modules\KoraPoints\Services\KoraPointLedgerService;
use App\Modules\KoraPoints\Services\KoraPointManagementService;
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

it('creates standalone points through web routes with optional GPS and documents', function (): void {
    $this->actingAs($this->commercial)->post(route('kora-points.store'), ['code' => 'KP-1', 'name' => 'Boutique Lomé', 'city' => 'Lomé', 'country' => 'TG', 'responsible_name' => 'Afi', 'phone' => '+22890000000'])->assertRedirect();
    $point = KoraPoint::query()->sole();
    expect($point->organization_id)->toBeNull()->and($point->latitude)->toBeNull()->and($point->agency->kind)->toBe('partner');
});

it('creates an organization and its points atomically and rolls back invalid batches', function (): void {
    $point = ['code' => 'POST-1', 'name' => 'Bureau central', 'city' => 'Lomé', 'country' => 'TG', 'responsible_name' => 'Afi', 'phone' => '+22890000000'];
    $this->actingAs($this->commercial)->post(route('kora-points.organizations.store'), ['code' => 'POST', 'name' => 'La Poste', 'points' => [$point, $point]])->assertSessionHasErrors('points.0.code');
    expect(KoraPointOrganization::query()->count())->toBe(0);
    $org = app(KoraPointManagementService::class)->saveOrganization($this->commercial, ['code' => 'POST', 'name' => 'La Poste', 'points' => [$point, array_replace($point, ['code' => 'POST-2'])]]);
    expect($org->points)->toHaveCount(2);
});

it('inherits terms without treating zero commission as inheritance and freezes sales', function (): void {
    $org = KoraPointOrganization::factory()->create(['commission_basis_points' => 400, 'ceiling_amount' => 50000, 'settlement_calendar' => kpCalendar()]);
    $point = KoraPoint::factory()->create(['organization_id' => $org->id]);
    $terms = app(KoraPointTermsService::class);
    expect($terms->resolve($point)['commission_basis_points'])->toBe(400);
    app(KoraPointManagementService::class)->updateTerms($this->commercial, $point, ['commission_basis_points' => 0]);
    $sale = kpSale($point->refresh());
    app(KoraPointManagementService::class)->updateTerms($this->commercial, $point, ['commission_basis_points' => 900]);
    expect($sale->refresh()->commission_amount)->toBe(0)->and($sale->terms_snapshot['commission_basis_points'])->toBe(0);
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
    $this->actingAs($supervisor->refresh())->get(route('kora-points.show', $point->uuid))->assertSuccessful()->assertInertia(fn (Assert $page) => $page->component('kora-points/show', false)->where('point.uuid', $point->uuid)->missing('point.id')->missing('point.agency_id'));
    $this->actingAs($supervisor->refresh())->get(route('kora-points.show', $other->uuid))->assertForbidden();
    $this->actingAs($supervisor->refresh())->post(route('kora-points.remittances.store'), [])->assertForbidden();
    $this->actingAs($agent)->get(route('administration.settings.operations'))->assertForbidden();
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
    $this->actingAs($newSupervisor)->get(route('kora-points.show', $point->uuid))->assertInertia(fn (Assert $page) => $page->component('kora-points/show', false)->where('sales.total', 0)->where('point.gross_sales_amount', 0));
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
