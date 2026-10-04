<?php

namespace App\Modules\CompanyAccounts\Actions;

use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;

class ChangeCompanyTravelerStatusAction
{
    public function execute(CompanyTraveler $traveler, CompanyTravelerStatus $status): CompanyTraveler
    {
        $traveler->update(['status' => $status]);

        return $traveler->refresh();
    }
}
