<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletHoldStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Captured = 'captured';
    case Released = 'released';

    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }
}
