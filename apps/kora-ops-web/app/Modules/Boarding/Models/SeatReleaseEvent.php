<?php

namespace App\Modules\Boarding\Models;

use App\Modules\Boarding\Data\SeatReleaseEventStatus;
use App\Modules\Support\Models\KoraModel;

class SeatReleaseEvent extends KoraModel
{
    protected $fillable = [
        'uuid',
        'ticket_id',
        'departure_id',
        'seat_id',
        'actual_stop_id',
        'released_segments',
        'operation_uuid',
        'local_recorded_at',
        'server_recorded_at',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => SeatReleaseEventStatus::class,
            'released_segments' => 'array',
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
        ];
    }
}
