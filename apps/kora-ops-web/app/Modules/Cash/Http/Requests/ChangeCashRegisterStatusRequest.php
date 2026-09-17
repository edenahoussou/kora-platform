<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Data\CashRegisterStatus;
use App\Modules\Cash\Models\CashRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeCashRegisterStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = $this->route('cashRegister');

        return $register instanceof CashRegister && ($this->user()?->can('update', $register->agency) ?? false);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(CashRegisterStatus::class)]];
    }
}
