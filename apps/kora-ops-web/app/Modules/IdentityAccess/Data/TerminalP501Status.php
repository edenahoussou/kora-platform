<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum TerminalP501Status: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Inactive = 'inactive';
    case Maintenance = 'maintenance';
    case Lost = 'lost';
}
