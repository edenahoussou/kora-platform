<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyAccountType: string
{
    case Company = 'company';
    case Ngo = 'ngo';
    case Embassy = 'embassy';
}
