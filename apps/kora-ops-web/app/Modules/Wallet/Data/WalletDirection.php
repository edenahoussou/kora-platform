<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Sens d'une écriture du ledger.
 */
enum WalletDirection: string
{
    use HasEnumUtilities;

    case Credit = 'credit';
    case Debit = 'debit';
}
