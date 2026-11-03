<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletTopUpStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
