<?php

namespace App\Modules\Sync\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SyncBatchStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Completed = 'completed';
}
