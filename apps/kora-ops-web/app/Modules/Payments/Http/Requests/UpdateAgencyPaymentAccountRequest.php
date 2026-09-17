<?php

namespace App\Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAgencyPaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agency')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array{label: string} */
    public function payload(): array
    {
        return ['label' => $this->string('label')->trim()->value()];
    }
}
