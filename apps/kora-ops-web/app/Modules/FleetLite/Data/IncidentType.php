<?php

namespace App\Modules\FleetLite\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum IncidentType: string
{
    use HasEnumUtilities;

    case Breakdown = 'breakdown';
    case Delay = 'delay';
    case Accident = 'accident';
    case Security = 'security';
    case Other = 'other';
}
