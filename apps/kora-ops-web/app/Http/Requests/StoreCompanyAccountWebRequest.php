<?php

namespace App\Http\Requests;

use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyAccountWebRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('company-accounts.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CompanyAccountType::class)],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'],
            'ifu' => ['nullable', 'string', 'max:64'],
            'rccm' => ['nullable', 'string', 'max:128'],
            'monthly_credit_limit' => ['required', 'integer', 'min:0'],
        ];
    }
}
