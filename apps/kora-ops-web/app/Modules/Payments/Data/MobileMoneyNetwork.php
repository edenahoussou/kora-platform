<?php

namespace App\Modules\Payments\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum MobileMoneyNetwork: string
{
    use HasEnumUtilities;

    case MtnMomo = 'mtn_momo';
    case MoovFlooz = 'moov_flooz';
    case CeltiisCash = 'celtiis_cash';

    public function label(): string
    {
        return match ($this) {
            self::MtnMomo => 'MTN MoMo',
            self::MoovFlooz => 'Moov Money / Flooz',
            self::CeltiisCash => 'Celtis Cash',
        };
    }
}
