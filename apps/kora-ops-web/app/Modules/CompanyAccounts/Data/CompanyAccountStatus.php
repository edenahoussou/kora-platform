<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyAccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
