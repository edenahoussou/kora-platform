<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\BusAmenity;

class UpdateBusAmenityAction
{
    /** @param array{code: string, label: string, icon: string|null, status: string} $attributes */
    public function execute(BusAmenity $amenity, array $attributes): BusAmenity
    {
        $amenity->update($attributes);

        return $amenity->refresh();
    }
}
