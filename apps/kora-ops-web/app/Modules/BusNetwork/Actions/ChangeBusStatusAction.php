<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\BusStatus;
use App\Modules\BusNetwork\Models\Bus;

class ChangeBusStatusAction
{
    public function execute(Bus $bus, BusStatus $status): Bus
    {
        $bus->update(['status' => $status]);

        return $bus->refresh();
    }
}
