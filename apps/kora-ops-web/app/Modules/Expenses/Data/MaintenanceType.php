<?php

namespace App\Modules\Expenses\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum MaintenanceType: string
{
    use HasEnumUtilities;

    case Preventive = 'preventive';
    case Corrective = 'corrective';
    case Diagnostic = 'diagnostic';

    public function label(): string
    {
        return match ($this) {
            self::Preventive => 'Préventive',
            self::Corrective => 'Corrective',
            self::Diagnostic => 'Diagnostic',
        };
    }
}
