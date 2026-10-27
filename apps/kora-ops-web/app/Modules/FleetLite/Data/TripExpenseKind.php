<?php

namespace App\Modules\FleetLite\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

/**
 * Dépense de voyage saisie par l'équipage depuis OPS Mobile (carnet de
 * bord). Chaque type correspond à une catégorie de dépense standard.
 */
enum TripExpenseKind: string
{
    use HasEnumUtilities;

    case Fuel = 'fuel';
    case Toll = 'toll';
    case BorderTax = 'border_tax';

    public function label(): string
    {
        return match ($this) {
            self::Fuel => 'Carburant',
            self::Toll => 'Péage',
            self::BorderTax => 'Taxe frontière',
        };
    }

    /** Code de la catégorie de dépense (`expense_categories.code`). */
    public function categoryCode(): string
    {
        return match ($this) {
            self::Fuel => 'fuel',
            self::Toll => 'tolls',
            self::BorderTax => 'border_taxes',
        };
    }

    public function categoryLabel(): string
    {
        return match ($this) {
            self::Fuel => 'Carburant',
            self::Toll => 'Péages',
            self::BorderTax => 'Taxes frontières',
        };
    }
}
