<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Foundation\Http\FormRequest;

class ImportCompanyTravelersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->get('companyAccount') instanceof CompanyAccount;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:102400'],
        ];
    }
}
