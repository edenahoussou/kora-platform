<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire de débit Wallet au guichet.
 */
class OpsWalletDebitRequest extends FormRequest
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
     * Valide la réservation, la répartition points/cash et la clé d'idempotence.
     *
     * @return array<string, array<int, string>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'reservation_uuid' => ['required', 'uuid', 'exists:reservations,uuid'],
            'wallet_amount' => ['required', 'integer', 'min:0'],
            'points_amount' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
