<?php

namespace App\Modules\Booking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ReservationSegmentStatus: string
{
    use HasEnumUtilities;

    case Held = 'held';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Ticketed = 'ticketed';
    case Boarded = 'boarded';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function occupiesSeat(): bool
    {
        return in_array($this, self::occupyingCases(), true);
    }

    /**
     * @return list<self>
     */
    public static function occupyingCases(): array
    {
        return [
            self::Held,
            self::Confirmed,
            self::Paid,
            self::Ticketed,
            self::Boarded,
        ];
    }

    /**
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::occupyingCases());
    }
}
