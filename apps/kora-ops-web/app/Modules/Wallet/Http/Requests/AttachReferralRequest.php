<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire de rattachement d'un code parrain.
 */
class AttachReferralRequest extends FormRequest
{
    /**
     * Autorise la requête pour tout voyageur authentifié.
     *
     * @return bool Toujours vrai, l'auth est portée par la route
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valide le code parrain saisi.
     *
     * @return array<string, array<int, string>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'referral_code' => ['required', 'string', 'max:24'],
        ];
    }
}
