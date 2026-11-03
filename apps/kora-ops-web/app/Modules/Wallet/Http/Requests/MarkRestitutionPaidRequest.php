<?php

namespace App\Modules\Wallet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkRestitutionPaidRequest extends FormRequest
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
            'payout_reference' => ['required', 'string', 'max:255'],
            'payout_channel' => ['nullable', 'string', 'max:32'],
        ];
    }
}
