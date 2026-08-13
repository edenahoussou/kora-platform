<?php

namespace App\Modules\Fiscalization\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum FiscalInvoiceStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Fiscalized = 'fiscalized';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
