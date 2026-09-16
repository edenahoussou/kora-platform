<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Models\CashRegister;
use Illuminate\Support\Facades\DB;

class UpdateCashRegisterAction
{
    /** @param list<string> $authorizedUserUuids */
    public function execute(CashRegister $register, string $label, array $authorizedUserUuids): CashRegister
    {
        return DB::transaction(function () use ($register, $label, $authorizedUserUuids): CashRegister {
            $register->update(['label' => trim($label)]);
            $userIds = User::query()
                ->where('agency_id', $register->agency_id)
                ->whereIn('uuid', $authorizedUserUuids)
                ->pluck('id')
                ->all();
            $register->authorizedUsers()->sync($userIds);

            return $register->fresh('authorizedUsers');
        });
    }
}
