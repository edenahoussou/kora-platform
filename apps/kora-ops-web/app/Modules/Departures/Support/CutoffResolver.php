<?php

namespace App\Modules\Departures\Support;

use App\Modules\Departures\Models\Departure;

class CutoffResolver
{
    public function resolve(Departure $departure): int
    {
        $departure->loadMissing(['agency', 'line']);
        $sellability = config('kora.departures.sellability', []);
        $overrides = is_array($sellability) && is_array($sellability['overrides'] ?? null)
            ? $sellability['overrides']
            : [];

        $agencyOverrides = is_array($overrides['agencies'] ?? null) ? $overrides['agencies'] : [];
        $lineOverrides = is_array($overrides['lines'] ?? null) ? $overrides['lines'] : [];

        $agencyUuid = $departure->relationLoaded('agency') ? $departure->agency?->uuid : null;
        if ($agencyUuid !== null && isset($agencyOverrides[$agencyUuid])) {
            return max(0, (int) $agencyOverrides[$agencyUuid]);
        }

        $lineUuid = $departure->relationLoaded('line') ? $departure->line?->uuid : null;
        if ($lineUuid !== null && isset($lineOverrides[$lineUuid])) {
            return max(0, (int) $lineOverrides[$lineUuid]);
        }

        return max(0, (int) (is_array($sellability) ? ($sellability['default_cutoff_minutes'] ?? 30) : 30));
    }
}
