<?php

namespace App\Modules\FleetLite\Models;

use App\Modules\FleetLite\Data\IncidentStatus;
use App\Modules\FleetLite\Data\IncidentType;
use App\Modules\Support\Models\KoraModel;
use Illuminate\Database\Eloquent\SoftDeletes;

class Incident extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'departure_id',
        'bus_id',
        'type',
        'description',
        'latitude',
        'longitude',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
        ];
    }
}
