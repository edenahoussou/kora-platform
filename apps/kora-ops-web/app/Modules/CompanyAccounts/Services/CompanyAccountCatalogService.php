<?php

namespace App\Modules\CompanyAccounts\Services;

use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditTransactionType;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyCreditHold;
use App\Modules\CompanyAccounts\Models\CompanyCreditTransaction;

class CompanyAccountCatalogService
{
    /** @return list<array<string, mixed>> */
    public function activeAccounts(): array
    {
        $periodKey = now()->format('Y-m');
        $accounts = CompanyAccount::query()
            ->where('status', 'active')
            ->with(['members' => fn ($query) => $query->where('role', 'referent')->where('status', 'active')->with('user')])
            ->orderBy('name')
            ->get();
        $ids = $accounts->modelKeys();

        $debits = CompanyCreditTransaction::query()
            ->whereIn('company_account_id', $ids)
            ->where('period_key', $periodKey)
            ->where('type', CompanyCreditTransactionType::Debit->value)
            ->selectRaw('company_account_id, SUM(amount) AS aggregate')
            ->groupBy('company_account_id')
            ->pluck('aggregate', 'company_account_id');
        $refunds = CompanyCreditTransaction::query()
            ->whereIn('company_account_id', $ids)
            ->where('period_key', $periodKey)
            ->where('type', CompanyCreditTransactionType::Refund->value)
            ->selectRaw('company_account_id, SUM(amount) AS aggregate')
            ->groupBy('company_account_id')
            ->pluck('aggregate', 'company_account_id');
        $holds = CompanyCreditHold::query()
            ->whereIn('company_account_id', $ids)
            ->where('period_key', $periodKey)
            ->where('status', CompanyCreditHoldStatus::Reserved->value)
            ->where('expires_at', '>', now())
            ->selectRaw('company_account_id, SUM(amount) AS aggregate')
            ->groupBy('company_account_id')
            ->pluck('aggregate', 'company_account_id');

        return array_values($accounts->map(function (CompanyAccount $account) use ($debits, $refunds, $holds): array {
            $referent = $account->members->first()?->user;
            $available = max(
                0,
                (int) $account->monthly_credit_limit
                    - (int) ($debits[$account->getKey()] ?? 0)
                    + (int) ($refunds[$account->getKey()] ?? 0)
                    - (int) ($holds[$account->getKey()] ?? 0),
            );

            return [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'type' => $account->getRawOriginal('type'),
                'reference' => $account->reference,
                'phone' => $account->phone,
                'email' => $account->email,
                'monthlyCreditLimit' => (int) $account->monthly_credit_limit,
                'availableCredit' => $available,
                'referent' => $referent ? ['uuid' => $referent->uuid, 'name' => $referent->name] : null,
            ];
        })->all());
    }
}
