<?php

namespace App\Modules\Booking\Models;

use App\Modules\Booking\Data\PassengerDocumentType;
use App\Modules\Support\Models\KoraModel;

class ReservationPassenger extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
        'first_name',
        'last_name',
        'phone',
        'document_type',
        'document_number',
        'nationality',
        'birth_date',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => PassengerDocumentType::class,
            'birth_date' => 'date',
        ];
    }
}
