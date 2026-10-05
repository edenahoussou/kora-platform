<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Modules\Booking\Http\Requests\CounterSaleSearchRequest;

class CompanyPortalSaleSearchRequest extends CounterSaleSearchRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function validationRoute(): string
    {
        return 'company-portal.sale.index';
    }
}
