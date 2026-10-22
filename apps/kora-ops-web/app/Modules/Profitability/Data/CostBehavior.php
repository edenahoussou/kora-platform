<?php

namespace App\Modules\Profitability\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum CostBehavior: string
{
    use HasEnumUtilities;

    case Fixed = 'fixed';
    case Variable = 'variable';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixe',
            self::Variable => 'Variable',
        };
    }
}
