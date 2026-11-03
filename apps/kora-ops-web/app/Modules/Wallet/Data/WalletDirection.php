<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletDirection: string
{
    use HasEnumUtilities;

    case Credit = 'credit';
    case Debit = 'debit';
}
