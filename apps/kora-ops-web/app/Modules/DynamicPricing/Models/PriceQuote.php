<?php

namespace App\Modules\DynamicPricing\Models;

use App\Modules\Support\Models\KoraModel;

class PriceQuote extends KoraModel
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
