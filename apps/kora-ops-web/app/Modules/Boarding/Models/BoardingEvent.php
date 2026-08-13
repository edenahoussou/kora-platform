<?php

namespace App\Modules\Boarding\Models;

use App\Modules\Boarding\Data\BoardingEventStatus;
use App\Modules\Support\Models\KoraModel;

class BoardingEvent extends KoraModel
{
    protected $fillable = [
        'uuid',
        'ticket_id',
        'departure_id',
        'terminal_p501_id',
        'operation_uuid',
        'local_recorded_at',
        'server_recorded_at',
        'status',
        'conflict_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => BoardingEventStatus::class,
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
            'conflict_payload' => 'array',
        ];
    }
}
