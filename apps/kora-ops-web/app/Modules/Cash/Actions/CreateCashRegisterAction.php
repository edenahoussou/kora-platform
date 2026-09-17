<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashRegisterKind;
use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Support\Facades\DB;

class CreateCashRegisterAction
{
    /** @param list<string> $authorizedUserUuids */
    public function execute(Agency $agency, string $label, array $authorizedUserUuids): CashRegister
    {
        return DB::transaction(function () use ($agency, $label, $authorizedUserUuids): CashRegister {
            $next = ((int) CashRegister::query()->where('agency_id', $agency->getKey())->where('kind', CashRegisterKind::Counter->value)->count()) + 1;
            $register = CashRegister::query()->create([
                'agency_id' => $agency->getKey(),
                'code' => 'G-'.$next,
                'label' => trim($label),
                'kind' => CashRegisterKind::Counter,
                'status' => CashRegisterStatus::Active,
            ]);

            $this->syncAuthorizedUsers($register, $agency, $authorizedUserUuids);

            return $register->load('authorizedUsers');
        });
    }

    /** @param list<string> $authorizedUserUuids */
    private function syncAuthorizedUsers(CashRegister $register, Agency $agency, array $authorizedUserUuids): void
    {
        $userIds = User::query()
            ->where('agency_id', $agency->getKey())
            ->whereIn('uuid', $authorizedUserUuids)
            ->pluck('id')
            ->all();
        $register->authorizedUsers()->sync($userIds);
    }
}
