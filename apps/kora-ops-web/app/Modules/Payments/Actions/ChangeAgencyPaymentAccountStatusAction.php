<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Payments\Models\AgencyPaymentAccount;

class ChangeAgencyPaymentAccountStatusAction
{
    public function execute(AgencyPaymentAccount $account, string $status): AgencyPaymentAccount
    {
        $account->update(['status' => $status]);

        return $account->refresh();
    }
}
