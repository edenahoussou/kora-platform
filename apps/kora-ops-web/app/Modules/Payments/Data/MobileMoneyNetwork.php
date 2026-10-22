<?php

namespace App\Modules\Payments\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum MobileMoneyNetwork: string
{
    use HasEnumUtilities;

    case MtnMomo = 'mtn_momo';
    case MoovFlooz = 'moov_flooz';
    case CeltiisCash = 'celtiis_cash';
    case MtnMomoCi = 'mtn_momo_ci';
    case MoovFloozCi = 'moov_flooz_ci';
    case WaveCi = 'wave_ci';
    case OrangeMoneyCi = 'orange_money_ci';
    case MoovFloozTg = 'moov_flooz_tg';
    case TmoneyTg = 'tmoney_tg';

    public function label(): string
    {
        return match ($this) {
            self::MtnMomo => 'MTN MoMo',
            self::MoovFlooz => 'Moov Money / Flooz',
            self::CeltiisCash => 'Celtis Cash',
            self::MtnMomoCi => 'MTN MoMo CI',
            self::MoovFloozCi => 'Moov Money CI',
            self::WaveCi => 'Wave CI',
            self::OrangeMoneyCi => 'Orange Money CI',
            self::MoovFloozTg => 'Moov Money TG',
            self::TmoneyTg => 'TMoney TG',
        };
    }

    /** Country code where this network operates. */
    public function country(): string
    {
        return match ($this) {
            self::MtnMomo, self::MoovFlooz, self::CeltiisCash => 'bj',
            self::MtnMomoCi, self::MoovFloozCi, self::WaveCi, self::OrangeMoneyCi => 'ci',
            self::MoovFloozTg, self::TmoneyTg => 'tg',
        };
    }
}
