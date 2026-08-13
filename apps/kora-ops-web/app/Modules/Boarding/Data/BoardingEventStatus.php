<?php

namespace App\Modules\Boarding\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum BoardingEventStatus: string
{
    use HasEnumUtilities;

    case Accepted = 'accepted';
    case Pending = 'pending';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
