<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ReservationChannel: string
{
    use HasEnumUtilities;

    case Online = 'online';
    case Counter = 'counter';
    case Mobile = 'mobile';
    case Ops = 'ops';
    case CompanyPortal = 'company_portal';
}
