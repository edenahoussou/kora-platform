<?php

namespace App\Modules\DynamicPricing\Actions;

use App\Modules\DynamicPricing\Data\FareStatus;
use App\Modules\DynamicPricing\Models\Fare;

class ChangeFareStatusAction
{
    public function execute(Fare $fare, FareStatus $status): Fare
    {
        $fare->update(['status' => $status]);

        return $fare->refresh();
    }
}
