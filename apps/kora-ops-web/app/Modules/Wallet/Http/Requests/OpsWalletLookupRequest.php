<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpsWalletLookupRequest extends FormRequest
{
    /**
     * Restricts wallet lookups to counter agents.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, array{description: string}>
     */
    public function queryParameters(): array
    {
        return [
            'phone' => ['description' => 'Numéro de téléphone personnel du titulaire du Wallet.'],
        ];
    }
}
