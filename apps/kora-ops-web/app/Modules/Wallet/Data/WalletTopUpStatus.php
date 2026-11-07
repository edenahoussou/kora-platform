<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Cycle de vie d'une recharge Wallet.
 */
enum WalletTopUpStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';

    /**
     * Indique si la recharge est confirmée ou en échec.
     *
     * @return bool Vrai si la recharge ne peut plus évoluer
     */
    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
