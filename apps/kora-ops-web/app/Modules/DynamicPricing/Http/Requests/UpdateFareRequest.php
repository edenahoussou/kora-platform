<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('fare')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'base_amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }

    /**
     * @return array{base_amount: int, currency: string, effective_from: string, effective_until: string|null}
     */
    public function payload(): array
    {
        return [
            'base_amount' => (int) $this->input('base_amount'),
            'currency' => strtoupper($this->string('currency')->trim()->value()),
            'effective_from' => $this->string('effective_from')->value(),
            'effective_until' => $this->filled('effective_until') ? $this->string('effective_until')->value() : null,
        ];
    }
}
