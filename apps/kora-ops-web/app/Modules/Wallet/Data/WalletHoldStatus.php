<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Cycle de vie d'une réservation de fonds.
 */
enum WalletHoldStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Captured = 'captured';
    case Released = 'released';

    /**
     * Indique si le hold est capturé ou libéré.
     *
     * @return bool Vrai si le hold ne peut plus évoluer
     */
    public function isTerminal(): bool
    {
        return $this !== self::Active;
    }
}
