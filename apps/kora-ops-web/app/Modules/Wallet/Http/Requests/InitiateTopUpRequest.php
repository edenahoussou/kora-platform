<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Payments\Data\MobileMoneyNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Formulaire de recharge Wallet.
 */
class InitiateTopUpRequest extends FormRequest
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
     * Valide montant, méthode, provider et coordonnées Mobile Money.
     *
     * @return array<string, array<int, mixed>> Règles de validation
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', Rule::in(['mobile_money', 'card'])],
            'provider' => ['required', 'string', Rule::in(['fedapay', 'mtn_momo', 'kkiapay'])],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'phone' => [
                Rule::requiredIf(fn (): bool => $this->input('method') === 'mobile_money' && $this->input('provider') !== 'kkiapay'),
                'nullable',
                'string',
                'max:32',
            ],
            'mobile_money_network' => [
                Rule::requiredIf(fn (): bool => $this->input('method') === 'mobile_money' && $this->input('provider') !== 'kkiapay'),
                'nullable',
                'string',
                Rule::in(MobileMoneyNetwork::values()),
            ],
        ];
    }

    /**
     * Extrait les données de recharge validées.
     *
     * @return array{amount: int, method: string, provider: string, idempotency_key: string, phone: string|null, mobile_money_network: string|null}
     */
    public function topUpData(): array
    {
        return [
            'amount' => $this->integer('amount'),
            'method' => $this->string('method')->value(),
            'provider' => $this->string('provider')->value(),
            'idempotency_key' => $this->string('idempotency_key')->value(),
            'phone' => $this->string('phone')->trim()->value() ?: null,
            'mobile_money_network' => $this->string('mobile_money_network')->value() ?: null,
        ];
    }
}
