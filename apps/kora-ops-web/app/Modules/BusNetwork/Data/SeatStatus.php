<?php

namespace App\Modules\BusNetwork\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SeatStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Blocked = 'blocked';
    case Maintenance = 'maintenance';

    public function canBeSold(): bool
    {
        return $this === self::Active;
    }
}
