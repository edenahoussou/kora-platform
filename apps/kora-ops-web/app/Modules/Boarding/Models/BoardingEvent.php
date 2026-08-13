<?php

namespace App\Modules\Boarding\Models;

use App\Modules\Support\Models\KoraModel;

class BoardingEvent extends KoraModel
{
    protected function casts(): array
    {
        return [
            'local_recorded_at' => 'datetime',
            'server_recorded_at' => 'datetime',
            'conflict_payload' => 'array',
        ];
    }
}
