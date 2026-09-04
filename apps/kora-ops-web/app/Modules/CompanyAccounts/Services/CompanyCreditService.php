<?php

namespace App\Modules\CompanyAccounts\Services;

use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyCreditService
{
    public function reserve(Reservation $reservation, CompanyAccount $companyAccount): CompanyCreditHold
    {
        $companyAccount = CompanyAccount::query()->whereKey($companyAccount->getKey())->lockForUpdate()->firstOrFail();
        if ($companyAccount->status !== CompanyAccountStatus::Active) {
            throw ValidationException::withMessages(['payer.company_account_uuid' => 'Le compte entreprise est inactif.']);
        }

        $periodKey = now()->format('Y-m');
        $this->releaseExpiredHolds($companyAccount, $periodKey);
        $amount = (int) $reservation->total_amount;
        if ($this->availableCredit($companyAccount, $periodKey) < $amount) {
            throw ValidationException::withMessages(['payer.company_account_uuid' => 'Le crédit mensuel disponible est insuffisant.']);
        }

        return CompanyCreditHold::query()->create([
            'company_account_id' => $companyAccount->getKey(),
            'reservation_id' => $reservation->getKey(),
            'amount' => $amount,
            'period_key' => $periodKey,
            'status' => CompanyCreditHoldStatus::Reserved,
            'expires_at' => $reservation->expires_at ?? now()->addMinutes(15),
        ]);
    }

    public function convert(CompanyCreditHold $hold, Payment $payment): CompanyCreditTransaction
    {
        CompanyAccount::query()->whereKey($hold->company_account_id)->lockForUpdate()->firstOrFail();
        $lockedHold = CompanyCreditHold::query()->whereKey($hold->getKey())->lockForUpdate()->firstOrFail();
        if ($lockedHold->status === CompanyCreditHoldStatus::Converted) {
            return CompanyCreditTransaction::query()
                ->where('reservation_id', $lockedHold->reservation_id)
                ->where('type', CompanyCreditTransactionType::Debit->value)
                ->firstOrFail();
        }
        if ($lockedHold->status !== CompanyCreditHoldStatus::Reserved || $lockedHold->expires_at->isPast()) {
            throw ValidationException::withMessages(['payment' => 'Le hold de crédit entreprise n’est plus actif.']);
        }

        $lockedHold->update(['status' => CompanyCreditHoldStatus::Converted, 'converted_at' => now()]);

        return CompanyCreditTransaction::query()->create([
            'company_account_id' => $lockedHold->company_account_id,
            'reservation_id' => $lockedHold->reservation_id,
            'payment_id' => $payment->getKey(),
            'type' => CompanyCreditTransactionType::Debit,
            'amount' => $lockedHold->amount,
            'period_key' => $lockedHold->period_key,
            'reference' => 'DEBIT-'.Str::upper(Str::random(24)),
            'metadata' => ['source' => 'company_credit_payment'],
        ]);
    }

    public function releaseExpiredHolds(CompanyAccount $companyAccount, ?string $periodKey = null): int
    {
        $query = $companyAccount->creditHolds()
            ->where('status', CompanyCreditHoldStatus::Reserved->value)
            ->where('expires_at', '<=', now());
        if ($periodKey !== null) {
            $query->where('period_key', $periodKey);
        }

        return $query->update([
            'status' => CompanyCreditHoldStatus::Released,
            'released_at' => now(),
        ]);
    }

    public function release(Reservation $reservation): void
    {
        $reservation->companyCreditHolds()
            ->where('status', CompanyCreditHoldStatus::Reserved->value)
            ->update([
                'status' => CompanyCreditHoldStatus::Released,
                'released_at' => now(),
            ]);
    }

    public function availableCredit(CompanyAccount $companyAccount, ?string $periodKey = null): int
    {
        $periodKey ??= now()->format('Y-m');
        $debits = $companyAccount->creditTransactions()
            ->where('period_key', $periodKey)
            ->where('type', CompanyCreditTransactionType::Debit->value)
            ->sum('amount');
        $refunds = $companyAccount->creditTransactions()
            ->where('period_key', $periodKey)
            ->where('type', CompanyCreditTransactionType::Refund->value)
            ->sum('amount');
        $holds = $companyAccount->creditHolds()
            ->where('period_key', $periodKey)
            ->where('status', CompanyCreditHoldStatus::Reserved->value)
            ->where('expires_at', '>', now())
            ->sum('amount');

        return max(0, (int) $companyAccount->monthly_credit_limit - (int) $debits + (int) $refunds - (int) $holds);
    }

    public function refund(Reservation $reservation, int $amount, ?Payment $payment = null): CompanyCreditTransaction
    {
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount' => 'Le montant du remboursement doit être positif.']);
        }

        $account = CompanyAccount::query()->whereKey($reservation->company_account_id)->lockForUpdate()->firstOrFail();

        return CompanyCreditTransaction::query()->create([
            'company_account_id' => $account->getKey(),
            'reservation_id' => $reservation->getKey(),
            'payment_id' => $payment?->getKey(),
            'type' => CompanyCreditTransactionType::Refund,
            'amount' => $amount,
            'period_key' => now()->format('Y-m'),
            'reference' => 'REFUND-'.Str::upper(Str::random(24)),
            'metadata' => ['source' => 'reservation_cancellation'],
        ]);
    }
}
