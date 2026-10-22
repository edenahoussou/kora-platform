<?php

namespace App\Modules\Profitability\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ProfitabilityAxis: string
{
    use HasEnumUtilities;

    case Bus = 'bus';
    case Line = 'line';
    case Departure = 'departure';
    case Agency = 'agency';
    case Period = 'period';

    public function label(): string
    {
        return match ($this) {
            self::Bus => 'Bus',
            self::Line => 'Ligne',
            self::Departure => 'Départ',
            self::Agency => 'Agence',
            self::Period => 'Période',
        };
    }

    public function labelPlural(): string
    {
        return match ($this) {
            self::Bus => 'Bus',
            self::Line => 'Lignes',
            self::Departure => 'Départs',
            self::Agency => 'Agences',
            self::Period => 'Périodes',
        };
    }
}
