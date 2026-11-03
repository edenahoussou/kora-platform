<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletBalanceType: string
{
    use HasEnumUtilities;

    case Cash = 'cash';
    case Points = 'points';
}
