<?php

namespace App\Modules\Sync\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SyncOperationStatus: string
{
    use HasEnumUtilities;

    case Accepted = 'accepted';
    case Pending = 'pending';
    case Conflict = 'conflict';
    case Rejected = 'rejected';
}
