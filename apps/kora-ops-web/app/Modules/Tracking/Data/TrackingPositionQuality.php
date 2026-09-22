<?php

namespace App\Modules\Tracking\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TrackingPositionQuality: string
{
    use HasEnumUtilities;

    case Accurate = 'accurate';
    case Degraded = 'degraded';
}
