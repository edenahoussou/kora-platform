<?php

namespace App\Modules\Payments\Http\Requests;

use App\Modules\Payments\Data\MobileMoneyNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
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
            'reservation_uuid' => ['required', 'uuid', 'exists:reservations,uuid'],
            'method' => ['required', 'string', Rule::in(['mobile_money', 'wallet'])],
            'provider' => ['required_if:method,mobile_money', 'nullable', 'string', Rule::in(['fedapay', 'mtn_momo'])],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'phone' => ['required_if:method,mobile_money', 'nullable', 'string', 'max:32'],
            'mobile_money_network' => ['required_if:method,mobile_money', 'nullable', 'string', Rule::in(MobileMoneyNetwork::values())],
            'wallet_amount' => ['required_if:method,wallet', 'nullable', 'integer', 'min:0'],
            'mobile_money_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     reservation_uuid: string,
     *     method: string,
     *     provider: string|null,
     *     idempotency_key: string,
     *     phone: string|null,
     *     mobile_money_network: string|null,
     *     wallet_amount: int|null,
     *     mobile_money_amount: int|null
     * }
     */
    public function paymentData(): array
    {
        return [
            'reservation_uuid' => $this->string('reservation_uuid')->value(),
            'method' => $this->string('method')->value(),
            'provider' => $this->string('provider')->value() ?: null,
            'idempotency_key' => $this->string('idempotency_key')->value(),
            'phone' => $this->string('phone')->trim()->value() ?: null,
            'mobile_money_network' => $this->string('mobile_money_network')->value() ?: null,
            'wallet_amount' => $this->input('wallet_amount') === null ? null : $this->integer('wallet_amount'),
            'mobile_money_amount' => $this->input('mobile_money_amount') === null ? null : $this->integer('mobile_money_amount'),
        ];
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'reservation_uuid' => ['description' => 'UUID de la réservation à payer.'],
            'method' => ['description' => 'Méthode de paiement.'],
            'provider' => ['description' => 'Fournisseur OAuth ou de paiement.'],
            'idempotency_key' => ['description' => 'Clé d’idempotence de la demande.'],
            'phone' => ['description' => 'Numéro de téléphone.'],
            'mobile_money_network' => ['description' => 'Réseau Mobile Money utilisé.'],
            'wallet_amount' => ['description' => 'Montant débité du portefeuille.'],
            'mobile_money_amount' => ['description' => 'Montant payé par Mobile Money.'],
        ];
    }
}
