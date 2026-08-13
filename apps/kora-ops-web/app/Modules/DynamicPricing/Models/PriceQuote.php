<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\Support\Models\KoraModel;

class PriceQuote extends KoraModel
{
    protected $fillable = [
        'uuid',
        'departure_id',
        'origin_stop_id',
        'destination_stop_id',
        'fare_id',
        'pricing_rule_id',
        'base_amount',
        'coefficient',
        'final_amount',
        'currency',
        'snapshot',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
