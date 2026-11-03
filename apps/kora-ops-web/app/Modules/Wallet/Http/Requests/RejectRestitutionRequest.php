<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire de rejet d'une restitution.
 */
class RejectRestitutionRequest extends FormRequest
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
     * Exige un motif de rejet.
     *
     * @return array<string, array<int, string>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
