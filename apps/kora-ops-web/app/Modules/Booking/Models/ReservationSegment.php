<?php

namespace App\Modules\Booking\Models;

use App\Modules\Support\Models\KoraModel;

class ReservationSegment extends KoraModel
{
    protected function casts(): array
    {
        return ['released_at' => 'datetime'];
    }
}
