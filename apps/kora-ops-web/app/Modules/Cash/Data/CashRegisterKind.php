<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashRegisterKind: string
{
    use HasEnumUtilities;

    case Counter = 'counter';
    case Vault = 'vault';
}
