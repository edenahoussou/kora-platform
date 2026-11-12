<?php

namespace App\Modules\KoraPoints\Http\Requests;

use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Foundation\Http\FormRequest;

class StoreKoraPartnerRemittanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->status === UserStatus::Active
            && $this->user()->hasAnyRole(['kora_point_supervisor', 'kora_point_treasurer'])
            && $this->user()->can('kora-points.remit');
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sale_uuids' => ['required', 'array', 'min:1', 'max:500'],
            'sale_uuids.*' => ['required', 'uuid', 'distinct'],
            'method' => ['required', 'in:bank_transfer,bank_deposit,check,mobile_money'],
            'reference' => ['required', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:102400'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
