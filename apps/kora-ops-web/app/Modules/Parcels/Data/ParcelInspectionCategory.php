<?php

namespace App\Modules\Parcels\Data;

enum ParcelInspectionCategory: string
{
    case Allowed = 'allowed';
    case Cash = 'cash';
    case Flammable = 'flammable';
    case Chemical = 'chemical';
    case UndeclaredMedicine = 'undeclared_medicine';
    case Weapon = 'weapon';
    case Refused = 'refused';

    public function isForbidden(): bool
    {
        return in_array($this, [
            self::Flammable,
            self::Chemical,
            self::UndeclaredMedicine,
            self::Weapon,
            self::Refused,
        ], true);
    }

    public function requiresCashDeclaration(): bool
    {
        return $this === self::Cash;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $category): string => $category->value, self::cases());
    }
}
