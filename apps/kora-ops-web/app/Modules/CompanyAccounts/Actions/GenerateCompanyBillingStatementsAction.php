<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyBillingStatementStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyBillingStatement;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;
use App\Notifications\CompanyAccounts\MonthlyBillingStatementNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Génère (ou régénère) le relevé de facturation mensuel d'un compte
 * organisationnel à partir des transactions crédit de la période.
 */
class GenerateCompanyBillingStatementsAction
{
    /** @return array{statements: int, emailed: int} */
    public function handle(?string $periodKey = null, ?CompanyAccount $onlyAccount = null): array
    {
        $periodKey ??= now()->subMonthNoOverflow()->format('Y-m');

        $accounts = CompanyAccount::query()
            ->where('status', CompanyAccountStatus::Active->value)
            ->when($onlyAccount !== null, fn ($query) => $query->whereKey($onlyAccount->getKey()))
            ->get();

        $emailed = 0;
        foreach ($accounts as $account) {
            $statement = $this->buildStatement($account, $periodKey);
            if ($this->dispatchToReferents($account, $statement)) {
                $emailed++;
            }
        }

        return ['statements' => $accounts->count(), 'emailed' => $emailed];
    }

    private function buildStatement(CompanyAccount $account, string $periodKey): CompanyBillingStatement
    {
        $transactions = $account->creditTransactions()
            ->where('period_key', $periodKey)
            ->with([
                'reservation.itineraryQuote.legs.originStop',
                'reservation.itineraryQuote.legs.destinationStop',
                'reservation.itineraryQuote.legs.departure',
                'reservation.fiscalInvoice',
            ])
            ->orderBy('created_at')
            ->get();

        $debitTotal = (int) $transactions
            ->where('type', CompanyCreditTransactionType::Debit)
            ->sum('amount');
        $refundTotal = (int) $transactions
            ->where('type', CompanyCreditTransactionType::Refund)
            ->sum('amount');

        $debitReservationIds = $transactions
            ->where('type', CompanyCreditTransactionType::Debit)
            ->pluck('reservation_id')
            ->filter()
            ->unique();

        $invoicesCount = $transactions
            ->map(fn (CompanyCreditTransaction $transaction) => $transaction->reservation?->fiscalInvoice)
            ->filter()
            ->unique('id')
            ->count();

        $lines = $transactions
            ->map(fn (CompanyCreditTransaction $transaction): array => $this->line($transaction))
            ->values()
            ->all();

        return DB::transaction(function () use ($account, $periodKey, $debitTotal, $refundTotal, $debitReservationIds, $invoicesCount, $lines): CompanyBillingStatement {
            $statement = CompanyBillingStatement::query()->firstOrNew([
                'company_account_id' => $account->getKey(),
                'period_key' => $periodKey,
            ]);

            $statement->fill([
                'reference' => $statement->reference ?? $this->reference($account, $periodKey),
                'status' => CompanyBillingStatementStatus::Generated,
                'debit_total' => $debitTotal,
                'refund_total' => $refundTotal,
                'net_total' => $debitTotal - $refundTotal,
                'reservations_count' => $debitReservationIds->count(),
                'invoices_count' => $invoicesCount,
                'lines' => $lines,
                'generated_at' => now(),
            ]);
            $statement->save();

            $statement->status = CompanyBillingStatementStatus::Available;
            $statement->save();

            return $statement;
        });
    }

    /** @return array<string, mixed> */
    private function line(CompanyCreditTransaction $transaction): array
    {
        $reservation = $transaction->reservation;
        $invoice = $reservation?->fiscalInvoice;

        return [
            'type' => $transaction->type->value,
            'reference' => $transaction->reference,
            'amount' => (int) $transaction->amount,
            'occurredAt' => $transaction->created_at?->toISOString(),
            'reservation' => $reservation === null ? null : [
                'uuid' => $reservation->uuid,
                'reference' => $reservation->reference,
                'itinerary' => $this->itinerary($reservation),
                'passengers' => (int) $reservation->group_size,
            ],
            'invoice' => $invoice === null ? null : [
                'uuid' => $invoice->uuid,
                'koraReference' => $invoice->kora_reference,
                'status' => $invoice->status->value,
                'totalWithTax' => (int) $invoice->total_with_tax,
            ],
        ];
    }

    private function itinerary(Reservation $reservation): string
    {
        $legs = $reservation->itineraryQuote?->legs?->sortBy('leg_order');
        $first = $legs?->first();
        $last = $legs?->last();

        if ($first === null || $last === null) {
            return 'Itinéraire indisponible';
        }

        return $first->originStop->name.' → '.$last->destinationStop->name;
    }

    private function reference(CompanyAccount $account, string $periodKey): string
    {
        return sprintf('RLV-%s-%s', $periodKey, $account->reference);
    }

    private function dispatchToReferents(CompanyAccount $account, CompanyBillingStatement $statement): bool
    {
        if ($statement->reservations_count === 0 || $statement->emailed_at !== null) {
            return false;
        }

        // Une notification par destinataire
        $referents = $account->members()
            ->where('role', CompanyMemberRole::Referent->value)
            ->where('status', CompanyMemberStatus::Active->value)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('email')
            ->values();

        $sent = false;
        foreach ($referents as $referent) {
            $referent->notify(
                new MonthlyBillingStatementNotification($account, $statement, $referent->name),
            );
            $sent = true;
        }

        if ($account->email !== null && $referents->doesntContain('email', $account->email)) {
            Notification::route('mail', [$account->email => $account->name])
                ->notify(new MonthlyBillingStatementNotification($account, $statement, $account->name));
            $sent = true;
        }

        if (! $sent) {
            return false;
        }

        $statement->forceFill(['emailed_at' => now()])->save();

        return true;
    }
}
