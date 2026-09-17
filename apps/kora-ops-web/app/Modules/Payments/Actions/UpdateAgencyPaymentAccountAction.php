<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Models\AgencyPaymentAccount;

class UpdateAgencyPaymentAccountAction
{
    /** @param array{label: string} $attributes */
    public function execute(AgencyPaymentAccount $account, array $attributes): AgencyPaymentAccount
    {
        $account->update(['label' => trim($attributes['label'])]);

        return $account->refresh();
    }
}
