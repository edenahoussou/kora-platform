<?php

namespace App\Modules\Payments\Data;

use App\Modules\Support\Enums\Concerns\HasEnumUtilities;

enum PaymentMethod: string
{
    use HasEnumUtilities;

    case Cash = 'cash';
    case MobileMoney = 'mobile_money';
    case Card = 'card';
    case Wallet = 'wallet';
    case CompanyCredit = 'company_credit';
}
