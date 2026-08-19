<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Stop;

class DeactivateStopAction
{
    public function execute(Stop $stop): Stop
    {
        $stop->update(['status' => StopStatus::Archived]);

        return $stop->refresh();
    }
}
