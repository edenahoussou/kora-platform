<?php

namespace App\Modules\Profitability\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Règle de répartition d'un poste de coût sur les départs d'une période.
 */
enum CostAllocationRule: string
{
    use HasEnumUtilities;

    case BusKm = 'bus_km';
    case EqualSplit = 'equal_split';

    public function label(): string
    {
        return match ($this) {
            self::BusKm => 'Au bus-km',
            self::EqualSplit => 'À parts égales',
        };
    }
}
