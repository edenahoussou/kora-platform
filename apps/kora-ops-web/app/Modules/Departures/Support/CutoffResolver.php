<?php

namespace App\Modules\Departures\Support;

use App\Modules\Departures\Models\Departure;
use App\Modules\Settings\Settings\OperationsSettings;

class CutoffResolver
{
    public function resolve(Departure $departure): int
    {
        $departure->loadMissing(['line']);
        $settings = app(OperationsSettings::class);
        $lineOverrides = $settings->saleCutoffOverrides;

        $lineUuid = $departure->relationLoaded('line') ? $departure->line?->uuid : null;
        if ($lineUuid !== null && isset($lineOverrides[$lineUuid])) {
            return max(0, (int) $lineOverrides[$lineUuid]);
        }

        return max(0, $settings->saleCutoffMinutes);
    }
}
