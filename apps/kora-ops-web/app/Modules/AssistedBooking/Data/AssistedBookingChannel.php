<?php

namespace App\Modules\AssistedBooking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum AssistedBookingChannel: string
{
    use HasEnumUtilities;

    case Call = 'call';
    case WhatsApp = 'whatsapp';
}
