<?php

namespace App\Modules\BusNetwork\Actions;

use App\Modules\BusNetwork\Data\StopStatus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeactivateStopAction
{
    public function execute(Stop $stop): Stop
    {
        return DB::transaction(function () use ($stop): Stop {
            Line::query()->whereKey($stop->line_id)->lockForUpdate()->firstOrFail();
            if ($stop->line->departures()->exists()) {
                throw ValidationException::withMessages(['status' => 'Ce parcours est utilisé par des départs publiés.']);
            }
            $stop->update(['status' => StopStatus::Archived]);

            return $stop->refresh();
        });

    }
}
