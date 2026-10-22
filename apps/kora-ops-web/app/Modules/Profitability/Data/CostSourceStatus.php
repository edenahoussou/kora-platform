<?php

namespace App\Modules\Profitability\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CostSourceStatus: string
{
    use HasEnumUtilities;

    case Available = 'available';
    case Pending = 'pending';
    case OutOfScope = 'out_of_scope';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Pending => 'En suspens',
            self::OutOfScope => 'Hors périmètre V1',
        };
    }
}
