<?php

namespace App\Modules\Cash\Models;

use App\Modules\Support\Models\KoraModel;

class CashSession extends KoraModel
{
    protected function casts(): array
    {
        return ['business_date' => 'date'];
    }
}
