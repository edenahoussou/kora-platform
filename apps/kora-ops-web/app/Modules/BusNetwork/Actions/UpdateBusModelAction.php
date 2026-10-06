<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Models\BusModel;

class UpdateBusModelAction
{
    /** @param array{brand: string, name: string, default_seat_count: int, status: string} $attributes */
    public function execute(BusModel $model, array $attributes): BusModel
    {
        $model->update($attributes);

        return $model->refresh();
    }
}
