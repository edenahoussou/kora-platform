<?php

namespace App\Modules\AssistedBooking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum AssistedBookingStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Cancelled = 'cancelled';
    case Refused = 'refused';
    case Unavailable = 'unavailable';
    case Expired = 'expired';
    case Finalized = 'finalized';

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Cancelled,
            self::Refused,
            self::Unavailable,
            self::Expired,
            self::Finalized,
        ], true);
    }
}
