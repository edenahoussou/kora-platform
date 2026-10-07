<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyBillingStatementStatus: string
{
    case Generated = 'generated';
    case Available = 'available';

    public function label(): string
    {
        return match ($this) {
            self::Generated => 'Généré',
            self::Available => 'Disponible',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
