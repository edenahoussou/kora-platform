<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashRegisterStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Inactive = 'inactive';
}
