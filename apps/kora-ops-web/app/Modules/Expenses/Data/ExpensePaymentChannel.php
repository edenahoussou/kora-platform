<?php

namespace App\Modules\Expenses\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum ExpensePaymentChannel: string
{
    use HasEnumUtilities;

    case Cash = 'cash';
    case MtnMomo = 'mtn_momo';
    case MoovFlooz = 'moov_flooz';
    case CeltiisCash = 'celtiis_cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::MtnMomo => 'MTN MoMo',
            self::MoovFlooz => 'Moov Money / Flooz',
            self::CeltiisCash => 'Celtis Cash',
        };
    }
}
