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
            'method' => ['required', 'string', Rule::in(['mobile_money', 'company_credit'])],
            'provider' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            // The action performs these checks after ownership/hold validation so
            // legacy clients still receive the most relevant reservation error.
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile_money_network' => ['nullable', 'string', Rule::in(MobileMoneyNetwork::values())],
        ];
    }

    /**
     * @return array{
     *     reservation_uuid: string,
     *     method: string,
     *     provider: string,
     *     idempotency_key: string,
     *     phone: string|null,
     *     mobile_money_network: string|null
     * }
     */
    public function paymentData(): array
    {
        return [
            'reservation_uuid' => $this->string('reservation_uuid')->value(),
            'method' => $this->string('method')->value(),
            'provider' => $this->string('provider')->value(),
            'idempotency_key' => $this->string('idempotency_key')->value(),
            'phone' => $this->string('phone')->trim()->value() ?: null,
            'mobile_money_network' => $this->string('mobile_money_network')->value() ?: null,
        ];
    }
}
