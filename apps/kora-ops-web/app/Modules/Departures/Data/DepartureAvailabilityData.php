<?php

namespace App\Modules\Departures\Data;

use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;

readonly class DepartureAvailabilityData
{
    /**
     * @param  array<string, mixed>|null  $farePreview
     */
    public function __construct(
        public Departure $departure,
        public Stop $originStop,
        public Stop $destinationStop,
        public int $availableSeatsCount,
        public ?array $farePreview,
        public DepartureSellabilityData $sellability,
    ) {}
}
