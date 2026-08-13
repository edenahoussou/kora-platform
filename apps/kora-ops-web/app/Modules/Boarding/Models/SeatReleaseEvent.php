<?php

namespace App\Modules\Boarding\Models;

use App\Modules\Support\Models\KoraModel;

class SeatReleaseEvent extends KoraModel
{
    protected function casts(): array
    {
        return [
            'released_segments' => 'array',
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
        ];
    }
}
