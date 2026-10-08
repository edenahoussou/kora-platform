<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\BusModel;

class CreateBusModelAction
{
    /** @param array{brand: string, name: string, default_seat_count: int, status: string} $attributes */
    public function execute(array $attributes): BusModel
    {
        return BusModel::query()->create($attributes);
    }
}
