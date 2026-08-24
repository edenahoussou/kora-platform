<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'method' => ['required', 'string', 'in:mobile_money,wallet,card,cash'],
            'provider' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'wallet_amount' => ['nullable', 'integer', 'min:0'],
            'points_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{
     *     reservation_uuid: string,
     *     method: string,
     *     provider: string,
     *     idempotency_key: string,
     *     phone: string|null,
     *     wallet_amount: int|null,
     *     points_amount: int|null
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
            'wallet_amount' => $this->has('wallet_amount') ? $this->integer('wallet_amount') : null,
            'points_amount' => $this->has('points_amount') ? $this->integer('points_amount') : null,
        ];
    }
}
