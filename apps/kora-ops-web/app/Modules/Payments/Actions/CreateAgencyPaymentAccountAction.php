<?php

namespace App\Modules\Payments\Actions;

use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Models\AgencyPaymentAccount;

class CreateAgencyPaymentAccountAction
{
    /**
     * @param  array{agency: Agency, network: MobileMoneyNetwork, label: string, account_identifier: string, status: string}  $attributes
     */
    public function execute(array $attributes): AgencyPaymentAccount
    {
        return AgencyPaymentAccount::query()->create([
            'agency_id' => $attributes['agency']->getKey(),
            'network' => $attributes['network'],
            'label' => $attributes['label'],
            'account_identifier' => $attributes['account_identifier'],
            'status' => $attributes['status'],
        ]);
    }
}
