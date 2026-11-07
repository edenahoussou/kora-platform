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
            'wallet_holder_phone' => ['required', 'string', 'max:32'],
            'wallet_holder_confirmed' => ['required', 'accepted'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Décrit les champs du corps pour la documentation OpenAPI.
     *
     * @return array<string, array{description: string}>
     */
    public function bodyParameters(): array
    {
        return [
            'reservation_uuid' => ['description' => 'UUID de la réservation à régler.'],
            'wallet_amount' => ['description' => 'Montant débité du solde cash du Wallet.'],
            'points_amount' => ['description' => 'Points fidélité utilisés.'],
            'wallet_holder_phone' => ['description' => 'Téléphone confirmé du titulaire présent.'],
            'wallet_holder_confirmed' => ['description' => 'Confirme que le titulaire est présent et autorise le montant affiché.'],
            'idempotency_key' => ['description' => 'Clé d’idempotence du débit.'],
        ];
    }
}
