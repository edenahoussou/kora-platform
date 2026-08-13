<?php

namespace App\Modules\Payments\Models;

use App\Modules\Support\Models\KoraModel;

class Payment extends KoraModel
{
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }
}
