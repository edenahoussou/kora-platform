<?php

namespace App\Modules\Departures\Models;

use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Departure extends KoraModel
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'estimated_arrival_at' => 'datetime',
        ];
    }
}
