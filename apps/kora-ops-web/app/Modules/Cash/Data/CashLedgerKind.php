<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashLedgerKind: string
{
    use HasEnumUtilities;

    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
}
