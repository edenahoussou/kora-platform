<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Wallet\Data\WalletBalanceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WalletAdjustmentRequest extends FormRequest
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
            'balance_type' => ['required', Rule::enum(WalletBalanceType::class)],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
