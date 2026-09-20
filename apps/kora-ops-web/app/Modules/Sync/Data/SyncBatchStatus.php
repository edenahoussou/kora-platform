<?php

namespace App\Modules\Sync\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SyncBatchStatus: string
{
    use HasEnumUtilities;

    case Pending = 'pending';
    case Completed = 'completed';

    /** Traitement interrompu par une erreur : le lot peut être rejoué avec le même `batch_uuid`. */
    case Failed = 'failed';
}
