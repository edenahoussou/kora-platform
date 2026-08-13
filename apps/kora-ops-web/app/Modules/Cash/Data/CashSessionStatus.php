<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashSessionStatus: string
{
    use HasEnumUtilities;

    case Open = 'open';
    case Closed = 'closed';
    case PendingApproval = 'pending_approval';
}
