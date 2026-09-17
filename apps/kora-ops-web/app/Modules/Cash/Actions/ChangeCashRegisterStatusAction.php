<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Services\CashRegisterBalance;
use Illuminate\Validation\ValidationException;

class ChangeCashRegisterStatusAction
{
    public function __construct(private readonly CashRegisterBalance $balances) {}

    public function execute(CashRegister $register, CashRegisterStatus $status): CashRegister
    {
        if ($register->kind === CashRegisterKind::Vault && $status === CashRegisterStatus::Inactive) {
            throw ValidationException::withMessages(['status' => 'Le coffre agence reste actif pour préserver le journal financier.']);
        }

        if ($status === CashRegisterStatus::Inactive) {
            if ($register->sessions()->whereIn('status', ['open', 'pending_approval'])->exists()) {
                throw ValidationException::withMessages(['status' => 'Clôturez la session ouverte avant de désactiver cette caisse.']);
            }
            if ($this->balances->published($register) > 0 || $this->balances->reserved($register) > 0) {
                throw ValidationException::withMessages(['status' => 'Régularisez le solde et les opérations en attente avant de désactiver cette caisse.']);
            }
        }

        $register->update(['status' => $status]);

        return $register->fresh();
    }
}
