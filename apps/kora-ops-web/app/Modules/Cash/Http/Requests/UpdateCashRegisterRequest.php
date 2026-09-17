<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashRegister;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = $this->route('cashRegister');

        return $register instanceof CashRegister && ($this->user()?->can('update', $register->agency) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'authorized_user_uuids' => ['nullable', 'array'],
            'authorized_user_uuids.*' => ['uuid', 'distinct'],
        ];
    }
}
