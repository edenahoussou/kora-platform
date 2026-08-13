<?php

namespace App\Modules\FleetLite\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum IncidentStatus: string
{
    use HasEnumUtilities;

    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';
}
