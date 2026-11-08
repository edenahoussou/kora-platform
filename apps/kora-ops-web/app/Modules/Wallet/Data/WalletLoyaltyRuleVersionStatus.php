<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum WalletLoyaltyRuleVersionStatus: string
{
    use HasEnumUtilities;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Superseded = 'superseded';
}
