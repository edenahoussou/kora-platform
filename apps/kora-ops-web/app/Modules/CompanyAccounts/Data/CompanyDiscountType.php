<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyDiscountType: string
{
    case Percentage = 'percentage';
    case Amount = 'amount';
}
