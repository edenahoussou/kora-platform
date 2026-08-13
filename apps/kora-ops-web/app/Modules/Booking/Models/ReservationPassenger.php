<?php

namespace App\Modules\Booking\Models;

use App\Modules\Support\Models\KoraModel;

class ReservationPassenger extends KoraModel
{
    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }
}
