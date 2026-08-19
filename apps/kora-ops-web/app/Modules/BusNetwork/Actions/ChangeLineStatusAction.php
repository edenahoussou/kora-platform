<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\LineStatus;
use App\Modules\BusNetwork\Models\Line;

class ChangeLineStatusAction
{
    public function execute(Line $line, LineStatus $status): Line
    {
        $line->update(['status' => $status]);

        return $line->refresh();
    }
}
