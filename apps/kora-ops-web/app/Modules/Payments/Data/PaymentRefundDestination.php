<?php

namespace App\Modules\Payments\Data;

enum PaymentRefundDestination: string
{
    case PartnerManual = 'partner_manual';
    case KoraManual = 'kora_manual';
    case WalletCash = 'wallet_cash';
    case CompanyCredit = 'company_credit';
}
