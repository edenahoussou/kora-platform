<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Actions\GenerateCompanyStatementPdfAction;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\Fiscalization\Actions\GenerateFiscalInvoicePdfAction;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Payments\Models\Payment;
use App\Notifications\CompanyAccounts\MonthlyBillingStatementNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function statementAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Horizon',
        'type' => 'company',
        'reference' => 'STMT-'.fake()->unique()->numerify('#####'),
        'email' => 'contact-'.Str::random(6).'@example.com',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        ...$attributes,
    ]);
}

/** @return array{0: User, 1: CompanyAccount} */
function statementReferent(array $accountAttributes = [], string $role = 'referent'): array
{
    $user = User::factory()->create();
    $account = statementAccount($accountAttributes);

    CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return [$user, $account];
}

function statementTransaction(CompanyAccount $account, string $type, int $amount, string $periodKey, ?Reservation $reservation = null): CompanyCreditTransaction
{
    return CompanyCreditTransaction::query()->create([
        'uuid' => (string) Str::uuid(),
        'company_account_id' => $account->id,
        'reservation_id' => $reservation?->id,
        'type' => $type,
        'amount' => $amount,
        'period_key' => $periodKey,
        'reference' => Str::upper($type).'-'.Str::upper(Str::random(16)),
    ]);
}

function statementReservation(CompanyAccount $account, array $attributes = []): Reservation
{
    return Reservation::query()->create([
        'company_account_id' => $account->id,
        'payer_type' => 'company',
        'purchase_type' => 'group',
        'group_size' => 2,
        'channel' => 'company_portal',
        'status' => 'ticketed',
        'total_amount' => 19000,
        'currency' => 'XOF',
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);
}

it('generates a monthly statement aggregating debits and refunds per account', function (): void {
    Notification::fake();

    [$referent, $account] = statementReferent();
    $period = '2026-08';

    $reservation = statementReservation($account);
    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'method' => 'company_credit',
        'provider' => 'company_credit',
        'provider_reference' => 'CC-'.Str::upper(Str::random(10)),
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 19000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'payer_name' => $account->name,
        'confirmed_at' => now(),
    ]);
    FiscalInvoice::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $payment->id,
        'kora_reference' => 'KORA-STMT-001',
        'status' => 'certified',
        'total_without_tax' => 19000,
        'total_tax' => 0,
        'total_with_tax' => 19000,
        'tax_snapshot' => [],
        'fiscalized_at' => now(),
    ]);

    statementTransaction($account, 'debit', 19000, $period, $reservation);
    statementTransaction($account, 'refund', 4000, $period, $reservation);
    statementTransaction($account, 'debit', 99000, '2026-09', $reservation);

    $this->artisan('companies:billing-statements', ['--period' => $period])
        ->assertSuccessful();

    $statement = CompanyBillingStatement::query()
        ->where('company_account_id', $account->id)
        ->where('period_key', $period)
        ->sole();

    expect($statement->status->value)->toBe('available')
        ->and($statement->debit_total)->toBe(19000)
        ->and($statement->refund_total)->toBe(4000)
        ->and($statement->net_total)->toBe(15000)
        ->and($statement->reservations_count)->toBe(1)
        ->and($statement->invoices_count)->toBe(1)
        ->and($statement->reference)->toBe("RLV-{$period}-{$account->reference}")
        ->and($statement->lines)->toHaveCount(2)
        ->and($statement->lines[0]['type'])->toBe('debit')
        ->and($statement->lines[0]['reservation']['uuid'])->toBe($reservation->uuid)
        ->and($statement->lines[0]['invoice']['koraReference'])->toBe('KORA-STMT-001')
        ->and($statement->lines[1]['type'])->toBe('refund')
        ->and($statement->emailed_at)->not->toBeNull();

    Notification::assertSentTo($referent, MonthlyBillingStatementNotification::class);
    Notification::assertSentOnDemand(MonthlyBillingStatementNotification::class);
});

it('regenerates a statement idempotently without duplicating or resending the email', function (): void {
    Notification::fake();

    [, $account] = statementReferent();
    $period = '2026-08';
    $reservation = statementReservation($account);
    statementTransaction($account, 'debit', 19000, $period, $reservation);

    $this->artisan('companies:billing-statements', ['--period' => $period])->assertSuccessful();
    $first = CompanyBillingStatement::query()->sole();
    $firstReference = $first->reference;
    $firstEmailedAt = $first->emailed_at;

    statementTransaction($account, 'debit', 8000, $period, statementReservation($account));

    $this->artisan('companies:billing-statements', ['--period' => $period])->assertSuccessful();

    expect(CompanyBillingStatement::query()->count())->toBe(1);

    $second = CompanyBillingStatement::query()->sole();
    expect($second->reference)->toBe($firstReference)
        ->and($second->debit_total)->toBe(27000)
        ->and($second->reservations_count)->toBe(2)
        ->and($second->emailed_at->toIso8601String())->toBe($firstEmailedAt->toIso8601String());

    // Une notification par destinataire : référent + email générique du compte.
    Notification::assertCount(2);
});

it('addresses the billing email to the referent by name', function (): void {
    Notification::fake();

    [$referent, $account] = statementReferent();
    $period = '2026-08';
    statementTransaction($account, 'debit', 19000, $period, statementReservation($account));

    $this->artisan('companies:billing-statements', ['--period' => $period])->assertSuccessful();

    Notification::assertSentTo(
        $referent,
        MonthlyBillingStatementNotification::class,
        fn (MonthlyBillingStatementNotification $notification): bool => $notification->recipientName === $referent->name,
    );
    Notification::assertSentOnDemand(
        MonthlyBillingStatementNotification::class,
        fn (MonthlyBillingStatementNotification $notification, array $channels, object $notifiable): bool => array_key_exists(
            $account->email,
            $notifiable->routes['mail'] ?? [],
        ),
    );
});

it('attaches the branded statement PDF and only certified sale invoices', function (): void {
    [, $account] = statementReferent();
    $period = '2026-08';

    $reservation = statementReservation($account);
    $payment = Payment::query()->create([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'method' => 'company_credit',
        'provider' => 'company_credit',
        'provider_reference' => 'CC-'.Str::upper(Str::random(10)),
        'idempotency_key' => (string) Str::uuid(),
        'amount' => 19000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'payer_name' => $account->name,
        'confirmed_at' => now(),
    ]);
    $saleInvoice = FiscalInvoice::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $payment->id,
        'kora_reference' => 'KORA-STMT-001',
        'status' => 'certified',
        'document_kind' => 'sale',
        'total_without_tax' => 19000,
        'total_tax' => 0,
        'total_with_tax' => 19000,
        'tax_snapshot' => [],
        'fiscalized_at' => now(),
    ]);
    $creditNote = FiscalInvoice::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $payment->id,
        'kora_reference' => 'KORA-AV-001',
        'status' => 'certified',
        'document_kind' => 'credit_note',
        'original_fiscal_invoice_id' => $saleInvoice->id,
        'total_without_tax' => 4000,
        'total_tax' => 0,
        'total_with_tax' => 4000,
        'tax_snapshot' => [],
        'fiscalized_at' => now(),
    ]);

    $statement = CompanyBillingStatement::query()->create([
        'company_account_id' => $account->id,
        'period_key' => $period,
        'reference' => "RLV-{$period}-{$account->reference}",
        'status' => 'available',
        'debit_total' => 19000,
        'refund_total' => 4000,
        'net_total' => 15000,
        'reservations_count' => 1,
        'invoices_count' => 2,
        'lines' => [
            [
                'type' => 'debit',
                'reference' => 'DEBIT-ABC',
                'amount' => 19000,
                'occurredAt' => now()->toISOString(),
                'reservation' => ['uuid' => $reservation->uuid, 'reference' => 'RES-1', 'itinerary' => 'Cotonou → Parakou', 'passengers' => 2],
                'invoice' => ['uuid' => $saleInvoice->uuid, 'koraReference' => 'KORA-STMT-001', 'status' => 'certified'],
            ],
            [
                'type' => 'refund',
                'reference' => 'REFUND-ABC',
                'amount' => 4000,
                'occurredAt' => now()->toISOString(),
                'reservation' => null,
                'invoice' => ['uuid' => $creditNote->uuid, 'koraReference' => 'KORA-AV-001', 'status' => 'certified'],
            ],
        ],
        'generated_at' => now(),
    ]);

    $statementPdf = Mockery::mock(GenerateCompanyStatementPdfAction::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturn(['content' => '%PDF-releve', 'filename' => 'releve-test.pdf'])
        ->getMock();
    $this->instance(GenerateCompanyStatementPdfAction::class, $statementPdf);

    $invoicePdf = Mockery::mock(GenerateFiscalInvoicePdfAction::class);
    $invoicePdf->shouldReceive('execute')
        ->once()
        ->with(Mockery::on(fn (FiscalInvoice $invoice): bool => $invoice->uuid === $saleInvoice->uuid))
        ->andReturn(['content' => '%PDF-facture', 'filename' => 'facture-KORA-STMT-001.pdf']);
    $this->instance(GenerateFiscalInvoicePdfAction::class, $invoicePdf);

    $message = (new MonthlyBillingStatementNotification($account, $statement, 'Kossi'))
        ->toMail(new User);

    expect($message->rawAttachments)->toHaveCount(2)
        ->and($message->rawAttachments[0]['name'])->toBe('releve-test.pdf')
        ->and($message->rawAttachments[1]['name'])->toBe('facture-KORA-STMT-001.pdf')
        ->and($message->rawAttachments[0]['options']['mime'])->toBe('application/pdf');
});

it('generates a statement without sending email when the period has no reservations', function (): void {
    Notification::fake();

    [, $account] = statementReferent();
    $period = '2026-08';

    $this->artisan('companies:billing-statements', ['--period' => $period])->assertSuccessful();

    $statement = CompanyBillingStatement::query()
        ->where('company_account_id', $account->id)
        ->sole();

    expect($statement->reservations_count)->toBe(0)
        ->and($statement->net_total)->toBe(0)
        ->and($statement->emailed_at)->toBeNull();

    Notification::assertNothingSent();
});

it('generates a statement for the previous month by default and skips suspended accounts', function (): void {
    Notification::fake();

    [, $active] = statementReferent();
    [, $suspended] = statementReferent(['status' => 'inactive']);

    $period = now()->subMonthNoOverflow()->format('Y-m');
    statementTransaction($active, 'debit', 5000, $period, statementReservation($active));
    statementTransaction($suspended, 'debit', 7000, $period, statementReservation($suspended));

    $this->artisan('companies:billing-statements')->assertSuccessful();

    expect(
        CompanyBillingStatement::query()
            ->where('company_account_id', $active->id)
            ->where('period_key', $period)
            ->exists(),
    )->toBeTrue();

    expect(
        CompanyBillingStatement::query()
            ->where('company_account_id', $suspended->id)
            ->exists(),
    )->toBeFalse();
});

it('rejects an invalid period option', function (): void {
    $this->artisan('companies:billing-statements', ['--period' => 'aout-2026'])
        ->assertFailed();
});

it('lists only the referent own account statements', function (): void {
    [, $account] = statementReferent();
    [, $otherAccount] = statementReferent();

    $own = CompanyBillingStatement::query()->create([
        'company_account_id' => $account->id,
        'period_key' => '2026-08',
        'reference' => 'RLV-2026-08-'.$account->reference,
        'status' => 'available',
        'debit_total' => 19000,
        'net_total' => 19000,
        'reservations_count' => 1,
        'generated_at' => now(),
    ]);
    CompanyBillingStatement::query()->create([
        'company_account_id' => $otherAccount->id,
        'period_key' => '2026-08',
        'reference' => 'RLV-2026-08-'.$otherAccount->reference,
        'status' => 'available',
        'generated_at' => now(),
    ]);

    $this->actingAs(statementReferent()[0])
        ->get(route('company-portal.statements.index'));

    $this->actingAs(CompanyAccountMember::query()->where('company_account_id', $account->id)->first()->user)
        ->get(route('company-portal.statements.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('company-portal/statements')
            ->has('statements.data', 1)
            ->where('statements.data.0.uuid', $own->uuid)
            ->where('statements.data.0.periodLabel', 'Août 2026')
            ->missing('statements.data.0.id')
        );
});

it('shows the statement detail with operation lines and denies other accounts', function (): void {
    [$referent, $account] = statementReferent();
    [, $otherAccount] = statementReferent();

    $statement = CompanyBillingStatement::query()->create([
        'company_account_id' => $account->id,
        'period_key' => '2026-08',
        'reference' => 'RLV-2026-08-'.$account->reference,
        'status' => 'available',
        'debit_total' => 19000,
        'net_total' => 19000,
        'reservations_count' => 1,
        'lines' => [
            [
                'type' => 'debit',
                'reference' => 'DEBIT-ABC',
                'amount' => 19000,
                'occurredAt' => now()->toISOString(),
                'reservation' => ['uuid' => (string) Str::uuid(), 'reference' => 'RES-1', 'itinerary' => 'Cotonou → Lomé', 'passengers' => 2],
                'invoice' => null,
            ],
        ],
        'generated_at' => now(),
    ]);
    $otherStatement = CompanyBillingStatement::query()->create([
        'company_account_id' => $otherAccount->id,
        'period_key' => '2026-08',
        'reference' => 'RLV-2026-08-'.$otherAccount->reference,
        'status' => 'available',
        'generated_at' => now(),
    ]);

    $this->actingAs($referent)
        ->get(route('company-portal.statements.show', ['statement' => $statement->uuid]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('company-portal/statement-detail')
            ->where('statement.reference', $statement->reference)
            ->where('statement.netTotal', 19000)
            ->has('statement.lines', 1)
            ->missing('statement.id')
        );

    $this->actingAs($referent)
        ->get(route('company-portal.statements.show', ['statement' => $otherStatement->uuid]))
        ->assertNotFound();
});

it('redirects guests and denies non-referents from the statements pages', function (): void {
    $this->get(route('company-portal.statements.index'))
        ->assertRedirect(route('login'));

    [$member] = statementReferent([], 'member');

    $this->actingAs($member)
        ->get(route('company-portal.statements.index'))
        ->assertForbidden();
});
