<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-19 12:00:00', RevenuePeriod::TIMEZONE));
    $this->seed(KoraBusRolePermissionSeeder::class);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('allows only the DAF, general management and system administrator to view and export revenue', function (): void {
    foreach (['daf', 'general_management', 'system_admin'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('revenue'))->assertSuccessful();
        $this->actingAs($user)->get(route('revenue.export'))->assertSuccessful();
    }

    foreach (['agency_manager', 'operations_director', 'commercial_director'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get(route('revenue'))->assertForbidden();
        $this->actingAs($user)->get(route('revenue.export'))->assertForbidden();
    }
});

it('builds inclusive Porto-Novo periods and selects adaptive timeline buckets', function (): void {
    $period = RevenuePeriod::fromFilters([
        'period' => 'custom',
        'from' => '2026-09-01',
        'to' => '2026-09-01',
    ]);

    expect($period->startUtc()->toIso8601String())->toBe('2026-08-31T23:00:00+00:00')
        ->and($period->endExclusiveUtc()->toIso8601String())->toBe('2026-09-01T23:00:00+00:00')
        ->and($period->comparisonLocalStart->toDateString())->toBe('2026-08-31')
        ->and($period->bucket())->toBe('day')
        ->and(RevenuePeriod::fromFilters(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-02-15'])->bucket())->toBe('week')
        ->and(RevenuePeriod::fromFilters(['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-07-01'])->bucket())->toBe('month')
        ->and(RevenuePeriod::fromFilters(['period' => 'last_year'])->localStart->toDateString())->toBe('2025-01-01')
        ->and(RevenuePeriod::fromFilters(['period' => 'last_year'])->localEnd->toDateString())->toBe('2025-12-31');
});

it('consolidates confirmed payments once and caps cancellation deductions by payment', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $agency = Agency::factory()->create(['name' => 'Agence Étoile']);

    $counterReservation = revenueReservation([
        'selling_agency_id' => $agency->id,
        'channel' => 'counter',
        'total_amount' => 10000,
    ]);
    $counterPayment = revenuePayment($counterReservation, [
        'amount' => 10000,
        'method' => 'cash',
        'confirmed_at' => CarbonImmutable::parse('2026-09-01 00:15:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    $onlineReservation = revenueReservation([
        'channel' => 'online',
        'total_amount' => 8000,
    ]);
    revenuePayment($onlineReservation, [
        'amount' => 8000,
        'method' => 'mobile_money',
        'confirmed_at' => CarbonImmutable::parse('2026-09-03 09:00:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    $creditReservation = revenueReservation([
        'selling_agency_id' => $agency->id,
        'channel' => 'mobile',
        'total_amount' => 5000,
    ]);
    revenuePayment($creditReservation, [
        'amount' => 5000,
        'method' => 'company_credit',
        'confirmed_at' => CarbonImmutable::parse('2026-09-04 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    $previousReservation = revenueReservation([
        'channel' => 'online',
        'total_amount' => 4000,
    ]);
    $previousPayment = revenuePayment($previousReservation, [
        'amount' => 4000,
        'method' => 'mobile_money',
        'confirmed_at' => CarbonImmutable::parse('2026-08-20 10:00:00', RevenuePeriod::TIMEZONE)->utc(),
    ]);

    revenueRefund($counterPayment, 6000, 'pending', '2026-09-05 08:00:00');
    revenueRefund($counterPayment, 6000, 'failed', '2026-09-06 08:00:00');
    revenueRefund($previousPayment, 1000, 'completed', '2026-09-07 08:00:00');

    $this->actingAs($daf)
        ->get(route('revenue', ['period' => 'this_month']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('revenue')
            ->where('summary.gross', 23000)
            ->where('summary.deductions', 11000)
            ->where('summary.net', 12000)
            ->where('comparison.net', 4000)
            ->where('comparison.percentage', 200)
            ->where('refunds.toCredit', 6000)
            ->where('refunds.toRegularize', 4000)
            ->where('refunds.credited', 1000)
            ->where('controls.refunds.exceededCount', 1)
            ->where('controls.refunds.exceededAmount', 2000)
            ->where('byChannel.0.key', 'counter')
            ->where('byChannel.0.gross', 10000)
            ->where('byChannel.0.deductions', 10000)
            ->where('byChannel.3.key', 'unclassified')
            ->where('byChannel.3.net', 7000)
            ->where('byPaymentMethod.3.key', 'company_credit')
            ->where('byPaymentMethod.3.gross', 5000)
            ->where('period.timezone', RevenuePeriod::TIMEZONE)
            ->where('period.bucket', 'day'));
});

it('keeps unlinked refunds and non-XOF payments out of totals while flagging them', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $reservation = revenueReservation(['channel' => 'ops', 'currency' => 'EUR', 'total_amount' => 80]);
    revenuePayment($reservation, [
        'amount' => 80,
        'currency' => 'EUR',
        'method' => 'cash',
        'confirmed_at' => now()->utc(),
    ]);
    $unlinkedReservation = revenueReservation(['channel' => 'online', 'total_amount' => 3000]);
    $refund = PaymentRefund::query()->create([
        'reservation_id' => $unlinkedReservation->id,
        'payment_id' => null,
        'amount' => 3000,
        'currency' => 'XOF',
        'destination' => 'wallet_cash',
        'status' => 'pending',
        'reference' => 'RFD-UNLINKED',
        'idempotency_key' => 'refund-unlinked',
    ]);
    $refund->forceFill(['created_at' => now()->utc(), 'updated_at' => now()->utc()])->saveQuietly();

    $this->actingAs($daf)
        ->get(route('revenue'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.gross', 0)
            ->where('summary.deductions', 0)
            ->where('controls.refunds.unlinkedCount', 1)
            ->where('controls.currencies.paymentCount', 1));
});

it('classifies the final sale channel and keeps passenger sales out of agency totals', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $agency = Agency::factory()->create(['name' => 'Agence Atlantique']);

    $counterReservation = revenueReservation([
        'selling_agency_id' => $agency->id,
        'channel' => 'counter',
        'total_amount' => 10000,
    ]);
    revenuePayment($counterReservation, [
        'amount' => 10000,
        'method' => 'mobile_money',
        'provider' => 'mtn_momo',
        'provider_reference' => 'COUNTER-MM-1',
        'mobile_money_network' => 'mtn_momo',
    ]);

    $passengerReservation = revenueReservation([
        'channel' => 'mobile',
        'total_amount' => 8000,
    ]);
    revenuePayment($passengerReservation, [
        'amount' => 8000,
        'method' => 'mobile_money',
        'provider' => 'fedapay',
        'provider_reference' => 'FEDA-1',
        'mobile_money_network' => 'mtn_momo',
    ]);

    $legacyOnlineReservation = revenueReservation([
        'channel' => 'online',
        'total_amount' => 3000,
    ]);
    revenuePayment($legacyOnlineReservation, [
        'amount' => 3000,
        'method' => 'mobile_money',
        'provider' => 'fedapay',
        'provider_reference' => 'FEDA-2',
        'mobile_money_network' => 'mtn_momo',
    ]);

    $legacyOpsReservation = revenueReservation([
        'channel' => 'ops',
        'total_amount' => 2000,
    ]);
    revenuePayment($legacyOpsReservation, [
        'amount' => 2000,
        'method' => 'cash',
    ]);

    $companyAccount = CompanyAccount::query()->create([
        'name' => 'Organisation Portail',
        'type' => 'company',
        'reference' => 'REV-PORTAL-01',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
    ]);
    $companyPortalReservation = revenueReservation([
        'company_account_id' => $companyAccount->id,
        'payer_type' => 'company',
        'channel' => 'company_portal',
        'total_amount' => 6000,
    ]);
    revenuePayment($companyPortalReservation, [
        'amount' => 6000,
        'method' => 'company_credit',
    ]);

    $this->actingAs($daf)
        ->get(route('revenue'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('byChannel.0.key', 'counter')
            ->where('byChannel.0.net', 10000)
            ->where('byChannel.1.key', 'mobile')
            ->where('byChannel.1.net', 11000)
            ->where('byChannel.2.key', 'company_portal')
            ->where('byChannel.2.net', 6000)
            ->where('byChannel.3.key', 'unclassified')
            ->where('byChannel.3.net', 2000)
            ->has('byAgency', 1)
            ->where('byAgency.0.name', 'Agence Atlantique')
            ->where('digitalWithoutAgency.name', 'Ventes digitales — hors agence')
            ->where('digitalWithoutAgency.net', 17000)
            ->where('paymentSources.0.label', 'FedaPay · MTN MoMo')
            ->where('paymentSources.0.net', 11000)
            ->where('paymentSources.2.key', 'company_portal:company_credit')
            ->where('paymentSources.2.label', 'Crédit entreprise · Espace entreprise'));
});

it('exports the same totals to six protected XLSX sheets and records the audit event', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $agency = Agency::factory()->create(['name' => '=AGENCE']);
    $reservation = revenueReservation([
        'reference' => '=SUM(1,1)',
        'selling_agency_id' => $agency->id,
        'channel' => 'counter',
        'total_amount' => 7000,
    ]);
    revenuePayment($reservation, [
        'amount' => 7000,
        'method' => 'cash',
        'confirmed_at' => now()->utc(),
    ]);

    $content = $this->actingAs($daf)
        ->get(route('revenue.export', ['period' => 'this_month']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();

    $path = tempnam(sys_get_temp_dir(), 'revenue-xlsx-');
    expect($path)->not->toBeFalse();
    file_put_contents($path, $content);
    $workbook = IOFactory::load($path);

    expect($workbook->getSheetNames())->toBe(['Synthèse', 'Agences', 'Canaux', 'Moyens de paiement', 'Écritures', 'Contrôles'])
        ->and($workbook->getSheetByName('Synthèse')?->getCell('B7')->getValue())->toBe(7000)
        ->and($workbook->getSheetByName('Écritures')?->getCell('E2')->getValue())->toBe("'=SUM(1,1)")
        ->and($workbook->getSheetByName('Écritures')?->getCell('F2')->getValue())->toBe("'=AGENCE")
        ->and(Activity::query()->where('event', 'revenue_exported')->exists())->toBeTrue();

    $workbook->disconnectWorksheets();
    unlink($path);
});

/** @param array<string, mixed> $attributes */
function revenueReservation(array $attributes = []): Reservation
{
    return Reservation::query()->create(array_merge([
        'status' => 'paid',
        'total_amount' => 5000,
        'currency' => 'XOF',
        'channel' => 'counter',
        'total_snapshot' => ['passenger_allocations' => []],
    ], $attributes));
}

/** @param array<string, mixed> $attributes */
function revenuePayment(Reservation $reservation, array $attributes = []): Payment
{
    return Payment::query()->create(array_merge([
        'reservation_id' => $reservation->id,
        'provider' => 'test',
        'idempotency_key' => 'payment-'.fake()->uuid(),
        'amount' => $reservation->total_amount,
        'currency' => $reservation->currency,
        'method' => 'cash',
        'status' => 'confirmed',
        'confirmed_at' => now()->utc(),
    ], $attributes));
}

function revenueRefund(Payment $payment, int $amount, string $status, string $localDate): PaymentRefund
{
    $refund = PaymentRefund::query()->create([
        'reservation_id' => $payment->reservation_id,
        'payment_id' => $payment->id,
        'amount' => $amount,
        'currency' => 'XOF',
        'destination' => 'wallet_cash',
        'status' => $status,
        'reference' => 'RFD-'.fake()->unique()->numerify('########'),
        'idempotency_key' => 'refund-'.fake()->uuid(),
    ]);
    $date = CarbonImmutable::parse($localDate, RevenuePeriod::TIMEZONE)->utc();
    $refund->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

    return $refund;
}
