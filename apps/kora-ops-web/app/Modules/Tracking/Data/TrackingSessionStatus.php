<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TrackingSessionStatus: string
{
    use HasEnumUtilities;

    case Open = 'open';
    case Closed = 'closed';
}
