<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyCreditTransactionType: string
{
    case Debit = 'debit';
    case Refund = 'refund';
}
