<?php

namespace App\Modules\Payments\Models;

use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Support\Models\KoraModel;

class Payment extends KoraModel
{
    protected $fillable = [
        'uuid',
        'reservation_id',
        'provider',
        'provider_reference',
        'idempotency_key',
        'amount',
        'currency',
        'status',
        'confirmed_at',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'confirmed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
