<?php

namespace App\Modules\Payments\Data;

enum PaymentRefundDestination: string
{
    case WalletCash = 'wallet_cash';
    case CompanyCredit = 'company_credit';
}
