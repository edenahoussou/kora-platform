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

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'name' => ['description' => 'Nom affiché ou nom de l’appareil.'],
            'type' => ['description' => 'Type de l’événement ou de la ressource.'],
            'reference' => ['description' => 'Valeur de « reference ».'],
            'phone' => ['description' => 'Numéro de téléphone.'],
            'email' => ['description' => 'Adresse email.'],
            'monthly_credit_limit' => ['description' => 'Plafond de crédit mensuel en unité monétaire.'],
        ];
    }
}
