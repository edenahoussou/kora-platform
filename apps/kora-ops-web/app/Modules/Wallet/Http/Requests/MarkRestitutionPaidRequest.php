<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire de versement d'une restitution.
 */
class MarkRestitutionPaidRequest extends FormRequest
{
    /**
     * Autorise la requête, la permission est vérifiée par le contrôleur.
     *
     * @return bool Toujours vrai
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Exige la référence du versement hors système.
     *
     * @return array<string, array<int, string>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'payout_reference' => ['required', 'string', 'max:255'],
            'payout_channel' => ['nullable', 'string', 'max:32'],
        ];
    }
}
