<?php

namespace App\Modules\Booking\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'total_snapshot' => 'array',
        ];
    }
}
