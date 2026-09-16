<?php

namespace App\Modules\Cash\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CashTransferStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
