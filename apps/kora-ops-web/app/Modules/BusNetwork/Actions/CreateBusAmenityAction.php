<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\BusAmenity;

class CreateBusAmenityAction
{
    /** @param array{code: string, label: string, icon: string|null, status: string} $attributes */
    public function execute(array $attributes): BusAmenity
    {
        return BusAmenity::query()->create($attributes);
    }
}
