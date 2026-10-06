<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\BusModelStatus;
use App\Modules\BusNetwork\Models\BusModel;

class ChangeBusModelStatusAction
{
    public function execute(BusModel $model, BusModelStatus $status): BusModel
    {
        $model->update(['status' => $status]);

        return $model->refresh();
    }
}
