<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashMovementStatus: string
{
    use HasEnumUtilities;

    case Posted = 'posted';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Reversed = 'reversed';
}
