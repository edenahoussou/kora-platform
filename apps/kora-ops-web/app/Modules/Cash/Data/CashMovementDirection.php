<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashMovementDirection: string
{
    use HasEnumUtilities;

    case In = 'in';
    case Out = 'out';
}
