<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum RestitutionStatus: string
{
    use HasEnumUtilities;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Paid = 'paid';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Paid], true);
    }
}
