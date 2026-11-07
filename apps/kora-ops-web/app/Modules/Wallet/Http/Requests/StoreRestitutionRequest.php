<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire de demande de restitution.
 */
class StoreRestitutionRequest extends FormRequest
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
     * Valide le Wallet cible, le montant et la note.
     *
     * @return array<string, array<int, string>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'wallet_uuid' => ['required', 'uuid', 'exists:wallets,uuid'],
            'holder_phone' => ['required', 'string', 'max:32'],
            'holder_confirmed' => ['required', 'accepted'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:1000'],
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
            'wallet_uuid' => ['description' => 'UUID du Wallet concerné.'],
            'holder_phone' => ['description' => 'Téléphone confirmé du titulaire présent.'],
            'holder_confirmed' => ['description' => 'Confirme le contrôle du titulaire et son accord pour la restitution.'],
            'amount' => ['description' => 'Montant de la restitution demandée.'],
            'note' => ['description' => 'Motif ou précision du demandeur.'],
        ];
    }
}
