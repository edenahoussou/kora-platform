<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyTravelerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
