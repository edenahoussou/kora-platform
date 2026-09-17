<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\IdentityAccess\Models\Agency;

class EnsureAgencyCashRegistersAction
{
    public function execute(Agency $agency): CashRegister
    {
        return CashRegister::query()->firstOrCreate(
            [
                'agency_id' => $agency->getKey(),
                'kind' => CashRegisterKind::Vault,
            ],
            [
                'code' => 'COFFRE',
                'label' => 'Coffre agence',
                'status' => CashRegisterStatus::Active,
            ],
        );
    }
}
