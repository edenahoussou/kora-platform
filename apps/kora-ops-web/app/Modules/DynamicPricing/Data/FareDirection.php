<?php

namespace App\Modules\DynamicPricing\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum FareDirection: string
{
    use HasEnumUtilities;

    case Outbound = 'outbound';
    case Return = 'return';
    case Both = 'both';
}
