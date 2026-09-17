<?php

namespace App\Modules\Cash\Services;

use App\Modules\Cash\Data\CashMovementStatus;
use App\Modules\Cash\Data\CashTransferStatus;
use App\Modules\Cash\Models\CashLedger;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Models\CashTransfer;
use App\Modules\Payments\Models\AgencyPaymentAccount;

class CashRegisterBalance
{
    public function published(CashRegister $register): int
    {
        $latestSession = $register->sessions()
            ->with(['ledgers' => fn ($query) => $query->where('kind', 'cash')])
            ->latest('business_date')
            ->latest('id')
            ->first();

        $query = $register->movements()
            ->whereIn('status', [CashMovementStatus::Posted->value, CashMovementStatus::Approved->value]);

        if ($latestSession instanceof CashSession && $latestSession->opened_at !== null) {
            $query->where(function ($movements) use ($latestSession): void {
                $movements->where('cash_session_id', $latestSession->getKey())
                    ->orWhere('occurred_at', '>=', $latestSession->opened_at);
            });
        }

        $movementBalance = (int) $query->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as balance")->value('balance');
        $cashLedger = $latestSession?->ledgers->first();
        $openingBalance = $cashLedger instanceof CashLedger ? $cashLedger->opening_balance : 0;

        return max(0, (int) $openingBalance + $movementBalance);
    }

    public function reserved(CashRegister $register): int
    {
        $transferAmount = (int) CashTransfer::query()
            ->where('source_cash_register_id', $register->getKey())
            ->where('status', CashTransferStatus::Pending->value)
            ->sum('amount');

        $movementAmount = (int) $register->movements()
            ->where('status', 'pending')
            ->where('direction', 'out')
            ->sum('amount');

        return $transferAmount + $movementAmount;
    }

    public function available(CashRegister $register): int
    {
        return max(0, $this->published($register) - $this->reserved($register));
    }

    /** @return array{cash: int, mobile_money: int, global: int} */
    public function agencyTotals(int $agencyId): array
    {
        $registers = CashRegister::query()
            ->where('agency_id', $agencyId)
            ->get();
        $cash = $registers->sum(fn (CashRegister $register): int => $this->published($register));
        $mobileMoney = AgencyPaymentAccount::query()
            ->where('agency_id', $agencyId)
            ->where('status', 'active')
            ->get()
            ->sum(fn (AgencyPaymentAccount $account): int => $this->mobileMoneyPublished($account));

        return [
            'cash' => $cash,
            'mobile_money' => $mobileMoney,
            'global' => $cash + $mobileMoney,
        ];
    }

    public function mobileMoneyPublished(AgencyPaymentAccount $account): int
    {
        $latestLedger = CashLedger::query()
            ->where('agency_payment_account_id', $account->getKey())
            ->with('cashSession')
            ->latest('id')
            ->first();

        if (! $latestLedger instanceof CashLedger) {
            return 0;
        }

        $movements = $latestLedger->movements()
            ->whereIn('status', [CashMovementStatus::Posted->value, CashMovementStatus::Approved->value]);
        $balance = (int) $movements->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as balance")->value('balance');

        return max(0, (int) $latestLedger->opening_balance + $balance);
    }
}
