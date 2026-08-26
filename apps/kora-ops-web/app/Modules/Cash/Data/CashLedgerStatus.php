<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashLedgerStatus: string
{
    use HasEnumUtilities;

    case Open = 'open';
    case PendingApproval = 'pending_approval';
    case Closed = 'closed';
}
