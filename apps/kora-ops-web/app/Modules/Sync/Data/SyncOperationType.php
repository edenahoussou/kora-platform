<?php

namespace App\Modules\Sync\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum SyncOperationType: string
{
    use HasEnumUtilities;

    case BoardingScan = 'boarding.scan';
    case BoardingNoShow = 'boarding.no_show';
    case SeatRelease = 'seat_release';

    /**
     * Les opérations qui libèrent un siège (no-show, descente anticipée)
     * sont synchronisées avant les embarquements — elles affectent la
     * disponibilité et la revente, donc le plus urgent à répercuter.
     */
    public function priority(): int
    {
        return match ($this) {
            self::SeatRelease, self::BoardingNoShow => 0,
            self::BoardingScan => 1,
        };
    }
}
