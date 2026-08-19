<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Models\Agency;

class ChangeAgencyStatusAction
{
    public function execute(Agency $agency, AgencyStatus $status): Agency
    {
        $agency->update(['status' => $status]);

        return $agency->refresh();
    }
}
