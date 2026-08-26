<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\BusAmenityStatus;
use App\Modules\BusNetwork\Models\BusAmenity;

class ChangeBusAmenityStatusAction
{
    public function execute(BusAmenity $amenity, BusAmenityStatus $status): BusAmenity
    {
        $amenity->update(['status' => $status]);

        return $amenity->refresh();
    }
}
