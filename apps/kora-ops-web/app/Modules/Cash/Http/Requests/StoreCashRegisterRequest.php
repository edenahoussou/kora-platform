<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agency = $this->route('agency');

        return $agency instanceof Agency && ($this->user()?->can('update', $agency) ?? false);
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
