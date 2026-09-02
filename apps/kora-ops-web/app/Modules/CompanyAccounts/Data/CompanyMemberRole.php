<?php

namespace App\Modules\CompanyAccounts\Data;

enum CompanyMemberRole: string
{
    case Referent = 'referent';
    case Member = 'member';
}
