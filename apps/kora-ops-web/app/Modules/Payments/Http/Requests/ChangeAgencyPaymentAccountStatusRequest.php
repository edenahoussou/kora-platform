<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangeAgencyPaymentAccountStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agency')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'in:active,inactive'],
            'replace_account_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function status(): string
    {
        return $this->string('status')->trim()->value();
    }

    public function replacementUuid(): ?string
    {
        return $this->filled('replace_account_uuid')
            ? $this->string('replace_account_uuid')->trim()->value()
            : null;
    }
}
