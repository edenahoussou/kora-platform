<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Cycle de vie d'une restitution exceptionnelle.
 */
enum RestitutionStatus: string
{
    use HasEnumUtilities;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    /**
     * Indique si la restitution est rejetée ou versée.
     *
     * @return bool Vrai si la restitution est clôturée
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Paid], true);
    }
}
