<?php

namespace App\Modules\Parcels\Models;

use App\Modules\Support\Models\KoraModel;

class ParcelIncident extends KoraModel
{
    protected $fillable = ['parcel_id', 'kind', 'description', 'blocking', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'blocking' => 'boolean',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
