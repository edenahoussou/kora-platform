<?php

namespace App\Modules\SeatInventory\Data;

use App\Modules\BusNetwork\Models\Seat;
use Carbon\CarbonImmutable;

readonly class SeatAvailabilityData
{
    public function __construct(
        public Seat $seat,
        public SeatAvailabilityStatus $availabilityStatus,
        public ?CarbonImmutable $heldUntil = null,
    ) {}
}
