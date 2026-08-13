<?php

namespace App\Modules\Ticketing\Models;

use App\Modules\Support\Models\KoraModel;
use App\Modules\Ticketing\Data\TicketStatus;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends KoraModel
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'reservation_id',
        'reservation_passenger_id',
        'qr_code',
        'short_code',
        'status',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'issued_at' => 'datetime',
        ];
    }
}
