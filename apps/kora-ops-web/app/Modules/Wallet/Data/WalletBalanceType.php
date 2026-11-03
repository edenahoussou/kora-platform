<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Nature du solde mouvementé : argent ou points fidélité.
 */
enum WalletBalanceType: string
{
    use HasEnumUtilities;

    case Cash = 'cash';
    case Points = 'points';
}
