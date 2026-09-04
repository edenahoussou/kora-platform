<?php

namespace App\Modules\CompanyAccounts\Http\Requests;

use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company-accounts.manage') ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CompanyAccountType::class)],
            'reference' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'monthly_credit_limit' => ['required', 'integer', 'min:0'],
        ];
    }
}
