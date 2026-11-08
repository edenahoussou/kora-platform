<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SimulateWalletLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'ticket_amount' => ['required', 'integer', 'between:1,100000000'],
            'points_used' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'qualifying_trips' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'first_usage' => ['sometimes', 'boolean'],
        ];
    }
}
