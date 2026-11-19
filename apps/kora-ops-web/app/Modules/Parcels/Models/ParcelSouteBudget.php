<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;

class ParcelSouteBudget extends KoraModel
{
    protected $fillable = ['departure_id', 'bus_id', 'capacity_grams'];
}
