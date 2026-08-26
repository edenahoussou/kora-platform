<?php

namespace App\Modules\Payments\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PaymentStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
