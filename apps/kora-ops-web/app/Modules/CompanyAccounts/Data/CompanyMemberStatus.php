<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyMemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
