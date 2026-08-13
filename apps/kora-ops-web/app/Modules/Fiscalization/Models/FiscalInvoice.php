<?php

namespace App\Modules\Fiscalization\Models;

use App\Modules\Support\Models\KoraModel;

class FiscalInvoice extends KoraModel
{
    protected function casts(): array
    {
        return [
            'tax_snapshot' => 'array',
            'fiscalized_at' => 'datetime',
        ];
    }
}
