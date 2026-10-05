<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Http\Requests\JsonValidationRequest;
use App\Modules\CompanyAccounts\Models\CompanyAccount;

class CompanyPortalPaymentRequest extends JsonValidationRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    protected function validationRoute(): string
    {
        return 'company-portal.sale.index';
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
