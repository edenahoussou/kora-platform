<?php

namespace App\Modules\IdentityAccess\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum AgencyStatus: string
{
    use HasEnumUtilities;

    case Active = 'active';
    case Draft = 'draft';
    case Suspended = 'suspended';
    case Archived = 'archived';
}
