<?php

namespace App\Http\Requests;

use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Foundation\Http\FormRequest;

class InviteCompanyReferentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', CompanyAccount::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'max:8'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du responsable est obligatoire.',
            'name.max' => 'Le nom du responsable ne doit pas dépasser :max caractères.',
            'email.required' => 'L’email du responsable est obligatoire.',
            'email.email' => 'L’email du responsable doit être une adresse valide.',
            'email.max' => 'L’email du responsable ne doit pas dépasser :max caractères.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser :max caractères.',
            'country_code.max' => 'L’indicatif pays ne doit pas dépasser :max caractères.',
        ];
    }
}
