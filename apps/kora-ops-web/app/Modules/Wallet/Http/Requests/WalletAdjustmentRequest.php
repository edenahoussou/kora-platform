<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Wallet\Data\WalletBalanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire d'ajustement Wallet audité.
 */
class WalletAdjustmentRequest extends FormRequest
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
     * Valide le solde, le sens, le montant et le motif obligatoire.
     *
     * @return array<string, array<int, mixed>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'balance_type' => ['required', Rule::enum(WalletBalanceType::class)],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
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
            'balance_type' => ['description' => 'Solde corrigé : cash ou points.'],
            'direction' => ['description' => 'Sens de l’écriture : credit ou debit.'],
            'amount' => ['description' => 'Montant de la correction.'],
            'reason' => ['description' => 'Motif obligatoire de la correction.'],
        ];
    }
}
