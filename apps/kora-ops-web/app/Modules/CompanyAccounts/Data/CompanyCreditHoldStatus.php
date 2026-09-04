<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyCreditHoldStatus: string
{
    case Reserved = 'reserved';
    case Released = 'released';
    case Converted = 'converted';
}
