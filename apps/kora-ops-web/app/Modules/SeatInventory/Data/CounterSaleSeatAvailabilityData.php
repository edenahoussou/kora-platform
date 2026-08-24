<?php

namespace App\Modules\SeatInventory\Data;

use App\Modules\BusNetwork\Models\Seat;
use Carbon\CarbonImmutable;

readonly class CounterSaleSeatAvailabilityData
{
    public function __construct(
        public Seat $seat,
        public CounterSaleSeatStatus $availabilityStatus,
        public ?CarbonImmutable $expiresAt,
    ) {}
}
