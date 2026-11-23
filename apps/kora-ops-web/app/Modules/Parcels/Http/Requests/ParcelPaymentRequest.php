<?php

namespace App\Modules\Parcels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParcelPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $routeName = (string) $this->route()?->getName();

        return ! str_starts_with($routeName, 'api.v1.parcels.')
            || $user->can('parcels.customer');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'pay' => [
                'idempotency_key' => ['required', 'uuid'],
                'method' => ['required', Rule::in($this->clientParcelRoute()
                    ? ['mobile_money', 'card', 'wallet']
                    : ['cash', 'mobile_money', 'card', 'wallet'])],
                'amount' => ['required', 'integer', 'min:1'],
                'provider' => ['nullable', 'string', 'max:32'],
                'mobile_money_network' => ['nullable', 'string', 'max:32'],
                'wallet_amount' => ['nullable', 'integer', 'min:0'],
                'points_amount' => ['nullable', 'integer', 'min:0'],
                'cash_received_amount' => ['nullable', 'integer', 'min:1'],
                'agency_payment_account_uuid' => ['nullable', 'uuid'],
                'provider_reference' => ['nullable', 'string', 'max:128'],
            ],
            'verify' => [
                'transaction_id' => ['nullable', 'string', 'max:128'],
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'method.in' => 'Le moyen de paiement cash n’est pas disponible depuis l’application.',
        ];
    }

    private function clientParcelRoute(): bool
    {
        return str_starts_with((string) $this->route()?->getName(), 'api.v1.parcels.');
    }
}
