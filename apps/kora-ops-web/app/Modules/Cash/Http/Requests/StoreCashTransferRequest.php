<?php

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCashTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash.transfer.create') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'source_cash_register_uuid' => ['nullable', 'uuid'],
            'destination_cash_register_uuid' => ['nullable', 'uuid'],
            'destination_type' => ['nullable', 'string', 'in:direction'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->validated();
    }
}
