<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ReservationStatus: string
{
    use HasEnumUtilities;

    case Held = 'held';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Ticketed = 'ticketed';
    case Boarded = 'boarded';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
