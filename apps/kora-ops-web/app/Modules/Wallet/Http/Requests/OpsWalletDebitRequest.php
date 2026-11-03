<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpsWalletDebitRequest extends FormRequest
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
            'wallet_amount' => ['required', 'integer', 'min:0'],
            'points_amount' => ['nullable', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }
}
