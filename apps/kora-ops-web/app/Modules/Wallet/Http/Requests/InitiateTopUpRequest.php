<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Payments\Data\MobileMoneyNetwork;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiateTopUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
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
